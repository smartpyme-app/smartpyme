<?php

namespace App\Services;

use App\Helpers\ShopifyHelper;
use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ShopifyConsolidationService
{
    protected ShopifyTransformer $transformer;
    protected ?ShopifyApiClient $apiClient;
    protected ShopifyTokenService $tokenService;

    public function __construct(
        ShopifyTransformer $transformer,
        ?ShopifyApiClient $apiClient = null,
        ?ShopifyTokenService $tokenService = null
    ) {
        $this->transformer = $transformer;
        $this->apiClient = $apiClient;
        $this->tokenService = $tokenService ?: app(ShopifyTokenService::class);
    }

    /**
     * Obtiene el cliente de API para la empresa.
     */
    public function getClient(Empresa $empresa): ShopifyApiClient
    {
        if ($this->apiClient) {
            return $this->apiClient;
        }

        return new ShopifyApiClient(
            $empresa->shopify_store_url,
            $empresa->shopify_consumer_secret,
            $this->tokenService,
            $empresa
        );
    }

    /**
     * Aplica throttling preventivo según el Leaky Bucket de Shopify (X-Shopify-Shop-Api-Call-Limit).
     */
    public function controlarRateLimit($response): void
    {
        if (!$response) {
            return;
        }

        $status = 200;
        $limitHeader = null;
        $retryAfter = 2;

        if (is_array($response)) {
            $status = $response['http_status'] ?? 200;
            $limitHeader = $response['call_limit'] ?? ($response['headers']['X-Shopify-Shop-Api-Call-Limit'][0] ?? null);
            $retryAfter = (int) ($response['headers']['Retry-After'][0] ?? 2);
        } elseif (is_object($response)) {
            if (method_exists($response, 'status')) {
                $status = $response->status();
            }
            if (method_exists($response, 'header')) {
                $limitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
                $retryAfter = (int) ($response->header('Retry-After') ?: 2);
            }
        }

        // Si Shopify retorna 429 (Too Many Requests), esperar Retry-After
        if ($status === 429) {
            ShopifyHelper::log("Shopify 429 Rate Limit alcanzado, durmiendo {$retryAfter}s...", [], 'warning');
            sleep($retryAfter + 1);
            return;
        }

        // Supervisión de la cubeta de llamadas (ej: 34/40)
        if ($limitHeader) {
            $parts = explode('/', $limitHeader);
            if (count($parts) === 2) {
                $used = (int) trim($parts[0]);
                $max = (int) trim($parts[1]);

                // Si se ha consumido más del 80% del bucket, aplicar pausa preventiva
                if ($max > 0 && $used >= ($max - 8)) {
                    usleep(500000); // 500ms
                }
            }
        }
    }

    /**
     * Obtiene todos los productos de Shopify utilizando paginación por cursor (Link rel="next").
     */
    public function obtenerTodosProductosShopify(ShopifyApiClient $client): array
    {
        $productos = [];
        $params = ['limit' => 250];

        do {
            $response = $client->get('products.json', $params);
            $this->controlarRateLimit($response);

            $batch = [];
            $linkHeader = null;

            if (is_array($response)) {
                $batch = $response['body']['products'] ?? [];
                $linkHeader = $response['link'] ?? ($response['headers']['Link'][0] ?? null);
            } elseif (is_object($response) && method_exists($response, 'json')) {
                if (method_exists($response, 'successful') && !$response->successful()) {
                    ShopifyHelper::log("Fallo al obtener productos de Shopify en consolidación", [
                        'status' => method_exists($response, 'status') ? $response->status() : null,
                        'body' => method_exists($response, 'body') ? $response->body() : null,
                    ], 'error');
                    break;
                }
                $data = $response->json();
                $batch = $data['products'] ?? [];
                $linkHeader = method_exists($response, 'header') ? $response->header('Link') : null;
            }

            if (empty($batch)) {
                break;
            }

            $productos = array_merge($productos, $batch);

            // Verificar si hay página siguiente en el encabezado Link
            $params = [];
            if ($linkHeader && strpos($linkHeader, 'rel="next"') !== false) {
                if (preg_match('/<[^>]*[?&]page_info=([^>&]+)[^>]*>;\s*rel="next"/', $linkHeader, $matches)) {
                    $params = ['page_info' => $matches[1], 'limit' => 250];
                }
            }
        } while (!empty($params));

        return $productos;
    }

    /**
     * DIRECCIÓN 1: Consolidar de Shopify hacia SmartPyme.
     * - Busca por IDs de Shopify para actualizar.
     * - Si no tiene IDs pero el SKU coincide con un producto local, lo VINCULA sin duplicar.
     * - Si no existe, crea la variante en SmartPyme.
     */
    public function consolidarShopifyHaciaSmartpyme(
        Empresa $empresa,
        User $user,
        array $opciones = [],
        ?callable $onProgreso = null
    ): array {
        $metricas = [
            'total' => 0,
            'procesados' => 0,
            'vinculados' => 0,
            'actualizados' => 0,
            'creados' => 0,
            'errores' => 0,
            'detalles' => [],
        ];

        $vincularSku = $opciones['vincular_sku'] ?? true;
        $actualizarPrecios = $opciones['actualizar_precios'] ?? true;
        $actualizarStock = $opciones['actualizar_stock'] ?? false;
        $crearNuevos = $opciones['crear_nuevos'] ?? true;

        $client = $this->getClient($empresa);
        if ($onProgreso) {
            $onProgreso(5, 'Descargando catálogo completo desde Shopify...', $metricas);
        }

        $productosShopify = $this->obtenerTodosProductosShopify($client);

        // Contabilizar variantes totales
        $totalVariantes = 0;
        foreach ($productosShopify as $p) {
            $totalVariantes += count($p['variants'] ?? []);
        }
        $metricas['total'] = $totalVariantes;

        if ($totalVariantes === 0) {
            if ($onProgreso) {
                $onProgreso(100, 'No se encontraron productos en Shopify.', $metricas);
            }
            return $metricas;
        }

        $idSucursal = $user->id_sucursal ?: 1;

        // Resolver ubicaciones multi-sucursal si se activó actualizar stock
        $idBodegaDefault = null;
        $ubicacionesMapeadas = collect();
        $mapaStockPorItemYBodega = []; // [inventory_item_id => [id_bodega => available_qty]]

        if ($actualizarStock) {
            $ubicacionesMapeadas = ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('sincronizar_stock', true)
                ->whereNotNull('id_bodega')
                ->get();

            $locDefault = $ubicacionesMapeadas->firstWhere('es_default', true)
                ?: ShopifyLocation::withoutGlobalScope('empresa')
                    ->where('id_empresa', $empresa->id)
                    ->where('es_default', true)
                    ->first();

            $idBodegaDefault = $locDefault ? $locDefault->id_bodega : ($user->id_bodega ?: null);

            // Si existen ubicaciones mapeadas, consultar niveles de inventario por ubicación de Shopify
            if ($ubicacionesMapeadas->isNotEmpty()) {
                if ($onProgreso) {
                    $onProgreso(8, 'Consultando existencias de todas las sucursales en Shopify...', $metricas);
                }

                foreach ($ubicacionesMapeadas as $locMap) {
                    $paramsInv = [
                        'location_ids' => $locMap->shopify_location_id,
                        'limit' => 250,
                    ];

                    do {
                        $resInv = $client->get('inventory_levels.json', $paramsInv);
                        $this->controlarRateLimit($resInv);

                        $levels = is_array($resInv)
                            ? ($resInv['body']['inventory_levels'] ?? [])
                            : (method_exists($resInv, 'json') ? ($resInv->json()['inventory_levels'] ?? []) : []);

                        $linkInv = is_array($resInv)
                            ? ($resInv['link'] ?? ($resInv['headers']['Link'][0] ?? null))
                            : (method_exists($resInv, 'header') ? $resInv->header('Link') : null);

                        foreach ($levels as $lvl) {
                            $itemId = $lvl['inventory_item_id'] ?? null;
                            if ($itemId) {
                                $mapaStockPorItemYBodega[$itemId][$locMap->id_bodega] = (float) ($lvl['available'] ?? 0);
                            }
                        }

                        $paramsInv = [];
                        if ($linkInv && strpos($linkInv, 'rel="next"') !== false) {
                            if (preg_match('/<[^>]*[?&]page_info=([^>&]+)[^>]*>;\s*rel="next"/', $linkInv, $m)) {
                                $paramsInv = ['page_info' => $m[1], 'limit' => 250];
                            }
                        }
                    } while (!empty($paramsInv));
                }
            }
        }

        foreach ($productosShopify as $prodShopify) {
            $filasTransformadas = $this->transformer->transformarProductoDesdeShopify(
                $prodShopify,
                $empresa->id,
                $user->id,
                $idSucursal
            );

            foreach ($filasTransformadas as $fila) {
                try {
                    $shopifyProdId = $fila['shopify_product_id'];
                    $shopifyVarId = $fila['shopify_variant_id'];
                    $sku = !empty($fila['codigo']) ? $fila['codigo'] : null;

                    // 1. Búsqueda por IDs directos de Shopify
                    $existente = Producto::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('shopify_product_id', $shopifyProdId)
                        ->where('shopify_variant_id', $shopifyVarId)
                        ->first();

                    if ($existente) {
                        // Actualizar información existente
                        $existente->nombre_variante = $fila['nombre_variante'];
                        $existente->option1_name = $fila['option1_name'] ?? null;
                        $existente->option1_value = $fila['option1_value'] ?? null;
                        $existente->option2_name = $fila['option2_name'] ?? null;
                        $existente->option2_value = $fila['option2_value'] ?? null;
                        $existente->option3_name = $fila['option3_name'] ?? null;
                        $existente->option3_value = $fila['option3_value'] ?? null;

                        if ($actualizarPrecios && isset($fila['precio'])) {
                            $existente->precio = $fila['precio'];
                        }
                        if (empty($existente->shopify_sku) && $sku) {
                            $existente->shopify_sku = $sku;
                        }
                        if (empty($existente->shopify_inventory_item_id) && !empty($fila['shopify_inventory_item_id'])) {
                            $existente->shopify_inventory_item_id = $fila['shopify_inventory_item_id'];
                        }

                        if (method_exists($existente, 'saveQuietly')) {
                            $existente->saveQuietly();
                        } else {
                            $existente->save();
                        }

                        $metricas['actualizados']++;
                    } else {
                        // 2. Si no tiene IDs, buscar por SKU para VINCULAR (evitar duplicado)
                        $productoVinculable = null;
                        if ($vincularSku && $sku) {
                            $productoVinculable = Producto::withoutGlobalScope('empresa')
                                ->where('id_empresa', $empresa->id)
                                ->where(function ($q) use ($sku) {
                                    $q->where('codigo', $sku)
                                      ->orWhere('shopify_sku', $sku);
                                })
                                ->whereNull('shopify_variant_id')
                                ->first();
                        }

                        if ($productoVinculable) {
                            // VINCULAR producto existente sin crear uno nuevo
                            $productoVinculable->shopify_product_id = $shopifyProdId;
                            $productoVinculable->shopify_variant_id = $shopifyVarId;
                            $productoVinculable->shopify_inventory_item_id = $fila['shopify_inventory_item_id'] ?? null;
                            $productoVinculable->shopify_sku = $sku;
                            if (!empty($fila['nombre_variante'])) {
                                $productoVinculable->nombre_variante = $fila['nombre_variante'];
                            }
                            if ($actualizarPrecios && isset($fila['precio'])) {
                                $productoVinculable->precio = $fila['precio'];
                            }

                            if (method_exists($productoVinculable, 'saveQuietly')) {
                                $productoVinculable->saveQuietly();
                            } else {
                                $productoVinculable->save();
                            }

                            $metricas['vinculados']++;
                        } elseif ($crearNuevos) {
                            // 3. Crear nueva variante en SmartPyme
                            Producto::create($fila);
                            $metricas['creados']++;
                        }
                    }

                    // Actualizar stock si corresponde (soporte multi-sucursal)
                    if ($actualizarStock) {
                        $prodActual = $existente ?: ($productoVinculable ?? Producto::withoutGlobalScope('empresa')
                            ->where('shopify_product_id', $shopifyProdId)
                            ->where('shopify_variant_id', $shopifyVarId)
                            ->first());

                        if ($prodActual) {
                            $invItemId = $fila['shopify_inventory_item_id'] ?? null;

                            Inventario::withoutEvents(function () use ($invItemId, $mapaStockPorItemYBodega, $idBodegaDefault, $fila, $prodActual) {
                                if ($invItemId && !empty($mapaStockPorItemYBodega[$invItemId])) {
                                    // Multi-sucursal: Asignar el stock correspondiente a cada bodega mapeada
                                    foreach ($mapaStockPorItemYBodega[$invItemId] as $bodegaId => $stockCantidad) {
                                        Inventario::updateOrCreate(
                                            [
                                                'id_producto' => $prodActual->id,
                                                'id_bodega' => $bodegaId,
                                            ],
                                            [
                                                'stock' => (float) $stockCantidad,
                                            ]
                                        );
                                    }
                                } elseif ($idBodegaDefault && isset($fila['stock'])) {
                                    // Fallback para mono-sucursal o sin mapeos específicos
                                    Inventario::updateOrCreate(
                                        [
                                            'id_producto' => $prodActual->id,
                                            'id_bodega' => $idBodegaDefault,
                                        ],
                                        [
                                            'stock' => (float) $fila['stock'],
                                        ]
                                    );
                                }
                            });
                        }
                    }
                } catch (\Throwable $e) {
                    $metricas['errores']++;
                    Log::error("Error al consolidar variante Shopify {$fila['shopify_variant_id']}: " . $e->getMessage());
                }

                $metricas['procesados']++;

                // Reportar progreso periódicamente
                if ($onProgreso && ($metricas['procesados'] % 10 === 0 || $metricas['procesados'] === $totalVariantes)) {
                    $porcentaje = (int) round(($metricas['procesados'] / $totalVariantes) * 90) + 5;
                    $onProgreso(
                        min(99, $porcentaje),
                        "Procesando variante {$metricas['procesados']} de {$totalVariantes}...",
                        $metricas
                    );
                }
            }
        }

        if ($onProgreso) {
            $onProgreso(100, 'Consolidación desde Shopify completada exitosamente.', $metricas);
        }

        return $metricas;
    }

    /**
     * DIRECCIÓN 2: Consolidar de SmartPyme hacia Shopify.
     * - Recorre los productos de SmartPyme.
     * - Para los no vinculados, consulta en Shopify si el SKU existe para enlazar los IDs.
     * - Si no existe en Shopify, crea el producto/variante en Shopify y guarda los IDs en SmartPyme.
     */
    public function consolidarSmartpymeHaciaShopify(
        Empresa $empresa,
        User $user,
        array $opciones = [],
        ?callable $onProgreso = null
    ): array {
        $metricas = [
            'total' => 0,
            'procesados' => 0,
            'vinculados' => 0,
            'actualizados' => 0,
            'creados' => 0,
            'errores' => 0,
            'detalles' => [],
        ];

        $client = $this->getClient($empresa);

        $vincularSku = $opciones['vincular_sku'] ?? true;
        $actualizarPrecios = $opciones['actualizar_precios'] ?? true;
        $actualizarStock = $opciones['actualizar_stock'] ?? false;
        $crearNuevos = $opciones['crear_nuevos'] ?? true;

        $ubicacionesMapeadas = collect();
        if ($actualizarStock) {
            $ubicacionesMapeadas = ShopifyLocation::withoutGlobalScope('empresa')
                ->where('id_empresa', $empresa->id)
                ->where('sincronizar_stock', true)
                ->whereNotNull('id_bodega')
                ->get();
        }

        if ($onProgreso) {
            $onProgreso(5, 'Consultando catálogo de SmartPyme...', $metricas);
        }

        // Obtener productos locales de la empresa
        $productos = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('enable', 1)
            ->whereNotNull('codigo')
            ->get();

        $total = $productos->count();
        $metricas['total'] = $total;

        if ($total === 0) {
            if ($onProgreso) {
                $onProgreso(100, 'No hay productos en SmartPyme para consolidar.', $metricas);
            }
            return $metricas;
        }

        // Si se va a vincular por SKU, descargar el mapa de SKUs existentes en Shopify
        $mapaSkusShopify = [];
        if ($vincularSku) {
            if ($onProgreso) {
                $onProgreso(10, 'Indexando variantes existentes en Shopify por SKU...', $metricas);
            }
            $productosShopify = $this->obtenerTodosProductosShopify($client);
            foreach ($productosShopify as $spProd) {
                foreach ($spProd['variants'] ?? [] as $spVar) {
                    if (!empty($spVar['sku'])) {
                        $mapaSkusShopify[trim((string) $spVar['sku'])] = [
                            'product_id' => $spProd['id'],
                            'variant_id' => $spVar['id'],
                            'inventory_item_id' => $spVar['inventory_item_id'] ?? null,
                            'title' => $spVar['title'] ?? '',
                        ];
                    }
                }
            }
        }

        foreach ($productos as $producto) {
            try {
                // Caso A: Ya tiene variant_id asignado -> Actualizar en Shopify
                if (!empty($producto->shopify_variant_id)) {
                    $variantPayload = [
                        'variant' => [
                            'id' => (int) $producto->shopify_variant_id,
                            'price' => number_format((float) $producto->precio, 2, '.', ''),
                        ],
                    ];
                    if (!empty($producto->codigo)) {
                        $variantPayload['variant']['sku'] = $producto->codigo;
                    }

                    $res = $client->put("variants/{$producto->shopify_variant_id}.json", $variantPayload);
                    $this->controlarRateLimit($res);

                    $exito = is_array($res) ? (($res['status'] ?? '') === 'success') : ($res && method_exists($res, 'successful') && $res->successful());

                    if ($exito) {
                        $metricas['actualizados']++;
                    } else {
                        $metricas['errores']++;
                    }
                } else {
                    // Caso B: No tiene IDs de Shopify
                    $skuLocal = trim((string) ($producto->codigo ?: $producto->shopify_sku));
                    $encontradoEnShopify = (!empty($skuLocal) && isset($mapaSkusShopify[$skuLocal]))
                        ? $mapaSkusShopify[$skuLocal]
                        : null;

                    if ($encontradoEnShopify) {
                        // VINCULAR los IDs en SmartPyme
                        $producto->shopify_product_id = $encontradoEnShopify['product_id'];
                        $producto->shopify_variant_id = $encontradoEnShopify['variant_id'];
                        $producto->shopify_inventory_item_id = $encontradoEnShopify['inventory_item_id'];
                        $producto->shopify_sku = $skuLocal;

                        if (method_exists($producto, 'saveQuietly')) {
                            $producto->saveQuietly();
                        } else {
                            $producto->save();
                        }

                        $metricas['vinculados']++;
                    } elseif ($crearNuevos) {
                        // Caso C: Crear producto nuevo en Shopify
                        $nuevoPayload = [
                            'product' => [
                                'title' => $producto->nombre,
                                'status' => 'active',
                                'variants' => [
                                    [
                                        'price' => number_format((float) $producto->precio, 2, '.', ''),
                                        'sku' => $producto->codigo,
                                    ]
                                ]
                            ]
                        ];

                        $res = $client->post('products.json', $nuevoPayload);
                        $this->controlarRateLimit($res);

                        $exito = is_array($res) ? (($res['status'] ?? '') === 'success') : ($res && method_exists($res, 'successful') && $res->successful());

                        if ($exito) {
                            $prodCreado = is_array($res) ? ($res['body']['product'] ?? null) : ($res->json()['product'] ?? null);
                            if ($prodCreado && !empty($prodCreado['variants'][0]['id'])) {
                                $producto->shopify_product_id = $prodCreado['id'];
                                $producto->shopify_variant_id = $prodCreado['variants'][0]['id'];
                                $producto->shopify_inventory_item_id = $prodCreado['variants'][0]['inventory_item_id'] ?? null;
                                $producto->shopify_sku = $producto->codigo;

                                if (method_exists($producto, 'saveQuietly')) {
                                    $producto->saveQuietly();
                                } else {
                                    $producto->save();
                                }

                                $metricas['creados']++;
                            }
                        } else {
                            $metricas['errores']++;
                        }
                    }
                }

                // Si está activo actualizar stock y tiene inventory_item_id, sincronizar stock por cada sucursal/ubicación en Shopify
                if ($actualizarStock && !empty($producto->shopify_inventory_item_id) && $ubicacionesMapeadas->isNotEmpty()) {
                    foreach ($ubicacionesMapeadas as $locMap) {
                        $stockBodega = Inventario::where('id_producto', $producto->id)
                            ->where('id_bodega', $locMap->id_bodega)
                            ->value('stock') ?? 0;

                        $resInv = $client->post('inventory_levels/set.json', [
                            'location_id' => $locMap->shopify_location_id,
                            'inventory_item_id' => $producto->shopify_inventory_item_id,
                            'available' => (int) $stockBodega,
                        ]);
                        $this->controlarRateLimit($resInv);
                    }
                }
            } catch (\Throwable $e) {
                $metricas['errores']++;
                Log::error("Error consolidando producto #{$producto->id} a Shopify: " . $e->getMessage());
            }

            $metricas['procesados']++;

            if ($onProgreso && ($metricas['procesados'] % 10 === 0 || $metricas['procesados'] === $total)) {
                $porcentaje = (int) round(($metricas['procesados'] / $total) * 85) + 15;
                $onProgreso(
                    min(99, $porcentaje),
                    "Consolidando producto {$metricas['procesados']} de {$total} hacia Shopify...",
                    $metricas
                );
            }
        }

        if ($onProgreso) {
            $onProgreso(100, 'Consolidación hacia Shopify completada exitosamente.', $metricas);
        }

        return $metricas;
    }
}
