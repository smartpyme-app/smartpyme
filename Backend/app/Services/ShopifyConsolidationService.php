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
    protected ?ShopifySyncCache $syncCache;

    public function __construct(
        ShopifyTransformer $transformer,
        ?ShopifyApiClient $apiClient = null,
        ?ShopifyTokenService $tokenService = null,
        ?ShopifySyncCache $syncCache = null
    ) {
        $this->transformer = $transformer;
        $this->apiClient = $apiClient;
        $this->tokenService = $tokenService ?: app(ShopifyTokenService::class);
        $this->syncCache = $syncCache ?: app(ShopifySyncCache::class);
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
            Log::channel('shopify_consolidacion')->warning("Shopify 429 Rate Limit alcanzado, durmiendo {$retryAfter}s...");
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
                    Log::channel('shopify_consolidacion')->error("Fallo al obtener productos de Shopify en consolidación", [
                        'status' => method_exists($response, 'status') ? $response->status() : null,
                        'body' => method_exists($response, 'body') ? $response->body() : null,
                    ]);
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

        Log::channel('shopify_consolidacion')->info("Iniciando consolidación Shopify -> SmartPyme", [
            'empresa_id' => $empresa->id,
            'opciones' => $opciones,
        ]);

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
                            if (isset($fila['precio_sin_iva'])) {
                                $existente->precio_sin_iva = $fila['precio_sin_iva'];
                            }
                            if (isset($fila['precio_con_iva'])) {
                                $existente->precio_con_iva = $fila['precio_con_iva'];
                            }
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
                                if (isset($fila['precio_sin_iva'])) {
                                    $productoVinculable->precio_sin_iva = $fila['precio_sin_iva'];
                                }
                                if (isset($fila['precio_con_iva'])) {
                                    $productoVinculable->precio_con_iva = $fila['precio_con_iva'];
                                }
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
                    Log::channel('shopify_consolidacion')->error("Error al consolidar variante Shopify {$fila['shopify_variant_id']}: " . $e->getMessage());
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

        Log::channel('shopify_consolidacion')->info("Consolidación Shopify -> SmartPyme completada exitosamente", [
            'empresa_id' => $empresa->id,
            'metricas' => [
                'total' => $metricas['total'],
                'procesados' => $metricas['procesados'],
                'vinculados' => $metricas['vinculados'],
                'actualizados' => $metricas['actualizados'],
                'creados' => $metricas['creados'],
                'errores' => $metricas['errores'],
            ],
        ]);

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

        Log::channel('shopify_consolidacion')->info("Iniciando consolidación SmartPyme -> Shopify", [
            'empresa_id' => $empresa->id,
            'opciones' => $opciones,
        ]);

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

        // Paso 1: Enlazar primero por SKU los productos no vinculados
        foreach ($productos as $producto) {
            if (empty($producto->shopify_variant_id)) {
                $skuLocal = trim((string) ($producto->codigo ?: $producto->shopify_sku));
                if (!empty($skuLocal) && isset($mapaSkusShopify[$skuLocal])) {
                    $encontrado = $mapaSkusShopify[$skuLocal];
                    $producto->shopify_product_id = $encontrado['product_id'];
                    $producto->shopify_variant_id = $encontrado['variant_id'];
                    $producto->shopify_inventory_item_id = $encontrado['inventory_item_id'];
                    $producto->shopify_sku = $skuLocal;

                    if (method_exists($producto, 'saveQuietly')) {
                        $producto->saveQuietly();
                    } else {
                        $producto->save();
                    }

                    $metricas['vinculados']++;
                }
            }
        }

        // Paso 2: Separar entre productos ya vinculados (actualizar) y no vinculados (crear agrupados)
        $vinculados = $productos->filter(fn($p) => !empty($p->shopify_variant_id));
        $noVinculados = $productos->filter(fn($p) => empty($p->shopify_variant_id));

        // 2.1 Procesar productos ya vinculados
        foreach ($vinculados as $producto) {
            try {
                if ($actualizarPrecios) {
                    $precioVenta = ($producto->precio_con_iva > 0) ? $producto->precio_con_iva : $producto->precio;
                    $variantPayload = [
                        'variant' => [
                            'id' => (int) $producto->shopify_variant_id,
                            'price' => number_format((float) $precioVenta, 2, '.', ''),
                        ],
                    ];
                    if (!empty($producto->codigo)) {
                        $variantPayload['variant']['sku'] = $producto->codigo;
                    }

                    if ($this->syncCache) {
                        $this->syncCache->lockSync($producto->id);
                    }

                    $res = $client->put("variants/{$producto->shopify_variant_id}.json", $variantPayload);
                    $this->controlarRateLimit($res);

                    $exito = is_array($res) ? (($res['status'] ?? '') === 'success') : ($res && method_exists($res, 'successful') && $res->successful());

                    if ($exito) {
                        $metricas['actualizados']++;
                    } else {
                        $metricas['errores']++;
                    }
                }

                $this->sincronizarStockVarianteShopify($client, $producto, $ubicacionesMapeadas, $actualizarStock);
            } catch (\Throwable $e) {
                $metricas['errores']++;
                Log::channel('shopify_consolidacion')->error("Error actualizando variante #{$producto->shopify_variant_id} a Shopify: " . $e->getMessage());
            }

            $metricas['procesados']++;
            $this->notificarProgreso($metricas, $total, $onProgreso);
        }

        // 2.2 Procesar productos NO vinculados (crear nuevos agrupando variantes)
        if ($crearNuevos && $noVinculados->isNotEmpty()) {
            // Agrupar por nombre normalizado para reunir variantes del mismo producto
            $gruposPorNombre = $noVinculados->groupBy(fn($p) => trim(mb_strtolower($p->nombre)));

            foreach ($gruposPorNombre as $nombreNorm => $grupo) {
                $primerProd = $grupo->first();

                try {
                    // Verificar si ya existe un producto padre en Shopify para este nombre
                    $existingShopifyProductId = Producto::withoutGlobalScope('empresa')
                        ->where('id_empresa', $empresa->id)
                        ->where('nombre', $primerProd->nombre)
                        ->whereNotNull('shopify_product_id')
                        ->value('shopify_product_id');

                    if ($existingShopifyProductId) {
                        // Caso 2.2.A: El producto padre ya existe en Shopify -> Agregar variantes a ese producto
                        foreach ($grupo as $prod) {
                            try {
                                $precioVenta = ($prod->precio_con_iva > 0) ? $prod->precio_con_iva : $prod->precio;
                                $varPayload = [
                                    'variant' => [
                                        'price' => number_format((float) $precioVenta, 2, '.', ''),
                                        'sku' => $prod->codigo,
                                    ]
                                ];

                                $opt1 = $prod->option1_value ?: ($prod->nombre_variante ?: null);
                                if ($opt1) {
                                    $varPayload['variant']['option1'] = $opt1;
                                }
                                if (!empty($prod->option2_value)) {
                                    $varPayload['variant']['option2'] = $prod->option2_value;
                                }
                                if (!empty($prod->option3_value)) {
                                    $varPayload['variant']['option3'] = $prod->option3_value;
                                }
                                if (!empty($prod->barcode)) {
                                    $varPayload['variant']['barcode'] = $prod->barcode;
                                }

                                if ($this->syncCache) {
                                    $this->syncCache->lockSync($prod->id);
                                }

                                $res = $client->post("products/{$existingShopifyProductId}/variants.json", $varPayload);
                                $this->controlarRateLimit($res);

                                $exito = is_array($res) ? (($res['status'] ?? '') === 'success') : ($res && method_exists($res, 'successful') && $res->successful());
                                if ($exito) {
                                    $varCreada = is_array($res) ? ($res['body']['variant'] ?? null) : ($res->json()['variant'] ?? null);
                                    if ($varCreada && !empty($varCreada['id'])) {
                                        $prod->shopify_product_id = $existingShopifyProductId;
                                        $prod->shopify_variant_id = $varCreada['id'];
                                        $prod->shopify_inventory_item_id = $varCreada['inventory_item_id'] ?? null;
                                        $prod->shopify_sku = $prod->codigo;

                                        if (method_exists($prod, 'saveQuietly')) {
                                            $prod->saveQuietly();
                                        } else {
                                            $prod->save();
                                        }

                                        $metricas['creados']++;
                                        $this->sincronizarStockVarianteShopify($client, $prod, $ubicacionesMapeadas, $actualizarStock);
                                    } else {
                                        $metricas['errores']++;
                                    }
                                } else {
                                    $metricas['errores']++;
                                }
                            } catch (\Throwable $e) {
                                $metricas['errores']++;
                                Log::channel('shopify_consolidacion')->error("Error agregando variante #{$prod->id} al producto Shopify #{$existingShopifyProductId}: " . $e->getMessage());
                            }

                            $metricas['procesados']++;
                            $this->notificarProgreso($metricas, $total, $onProgreso);
                        }
                    } else {
                        // Caso 2.2.B: Producto nuevo completo en Shopify
                        $esAgrupado = ($grupo->count() > 1 || !empty($primerProd->option1_name));

                        if ($esAgrupado) {
                            $options = [];
                            if (!empty($primerProd->option1_name)) {
                                $options[] = ['name' => $primerProd->option1_name];
                            }
                            if (!empty($primerProd->option2_name)) {
                                $options[] = ['name' => $primerProd->option2_name];
                            }
                            if (!empty($primerProd->option3_name)) {
                                $options[] = ['name' => $primerProd->option3_name];
                            }
                            if (empty($options)) {
                                $options[] = ['name' => 'Opción'];
                            }

                            $variants = [];
                            foreach ($grupo as $prod) {
                                $precioVenta = ($prod->precio_con_iva > 0) ? $prod->precio_con_iva : $prod->precio;
                                $v = [
                                    'price' => number_format((float) $precioVenta, 2, '.', ''),
                                    'sku' => $prod->codigo,
                                    'option1' => $prod->option1_value ?: ($prod->nombre_variante ?: 'Default'),
                                ];
                                if (!empty($prod->option2_value)) {
                                    $v['option2'] = $prod->option2_value;
                                }
                                if (!empty($prod->option3_value)) {
                                    $v['option3'] = $prod->option3_value;
                                }
                                if (!empty($prod->barcode)) {
                                    $v['barcode'] = $prod->barcode;
                                }
                                $variants[] = $v;
                            }

                            $nuevoPayload = [
                                'product' => [
                                    'title' => $primerProd->nombre,
                                    'status' => 'active',
                                    'options' => $options,
                                    'variants' => $variants,
                                ]
                            ];
                        } else {
                            $precioVenta = ($primerProd->precio_con_iva > 0) ? $primerProd->precio_con_iva : $primerProd->precio;
                            $nuevoPayload = [
                                'product' => [
                                    'title' => $primerProd->nombre,
                                    'status' => 'active',
                                    'variants' => [
                                        [
                                            'price' => number_format((float) $precioVenta, 2, '.', ''),
                                            'sku' => $primerProd->codigo,
                                        ]
                                    ]
                                ]
                            ];
                        }

                        if (!empty($primerProd->descripcion_completa) || !empty($primerProd->descripcion)) {
                            $nuevoPayload['product']['body_html'] = $primerProd->descripcion_completa ?: $primerProd->descripcion;
                        }

                        foreach ($grupo as $prod) {
                            if ($this->syncCache) {
                                $this->syncCache->lockSync($prod->id);
                            }
                        }

                        $res = $client->post('products.json', $nuevoPayload);
                        $this->controlarRateLimit($res);

                        $exito = is_array($res) ? (($res['status'] ?? '') === 'success') : ($res && method_exists($res, 'successful') && $res->successful());

                        if ($exito) {
                            $prodCreado = is_array($res) ? ($res['body']['product'] ?? null) : ($res->json()['product'] ?? null);
                            $variantsShopify = $prodCreado['variants'] ?? [];

                            foreach ($grupo as $idx => $prod) {
                                $skuBuscado = trim((string) $prod->codigo);
                                $varMatch = null;
                                foreach ($variantsShopify as $vShopify) {
                                    if (!empty($skuBuscado) && ($vShopify['sku'] ?? '') === $skuBuscado) {
                                        $varMatch = $vShopify;
                                        break;
                                    }
                                }
                                if (!$varMatch && isset($variantsShopify[$idx])) {
                                    $varMatch = $variantsShopify[$idx];
                                }

                                if ($varMatch) {
                                    $prod->shopify_product_id = $prodCreado['id'];
                                    $prod->shopify_variant_id = $varMatch['id'];
                                    $prod->shopify_inventory_item_id = $varMatch['inventory_item_id'] ?? null;
                                    $prod->shopify_sku = $prod->codigo;

                                    if (method_exists($prod, 'saveQuietly')) {
                                        $prod->saveQuietly();
                                    } else {
                                        $prod->save();
                                    }

                                    $metricas['creados']++;
                                    $this->sincronizarStockVarianteShopify($client, $prod, $ubicacionesMapeadas, $actualizarStock);
                                } else {
                                    $metricas['errores']++;
                                }

                                $metricas['procesados']++;
                                $this->notificarProgreso($metricas, $total, $onProgreso);
                            }
                        } else {
                            $metricas['errores'] += $grupo->count();
                            $metricas['procesados'] += $grupo->count();
                            $this->notificarProgreso($metricas, $total, $onProgreso);
                        }
                    }
                } catch (\Throwable $e) {
                    $metricas['errores'] += $grupo->count();
                    $metricas['procesados'] += $grupo->count();
                    Log::channel('shopify_consolidacion')->error("Error creando producto agrupado '{$primerProd->nombre}' en Shopify: " . $e->getMessage());
                    $this->notificarProgreso($metricas, $total, $onProgreso);
                }
            }
        } elseif (!$crearNuevos && $noVinculados->isNotEmpty()) {
            foreach ($noVinculados as $nv) {
                $metricas['procesados']++;
                $this->notificarProgreso($metricas, $total, $onProgreso);
            }
        }

        if ($onProgreso) {
            $onProgreso(100, 'Consolidación hacia Shopify completada exitosamente.', $metricas);
        }

        Log::channel('shopify_consolidacion')->info("Consolidación SmartPyme -> Shopify completada exitosamente", [
            'empresa_id' => $empresa->id,
            'metricas' => [
                'total' => $metricas['total'],
                'procesados' => $metricas['procesados'],
                'vinculados' => $metricas['vinculados'],
                'actualizados' => $metricas['actualizados'],
                'creados' => $metricas['creados'],
                'errores' => $metricas['errores'],
            ],
        ]);

        return $metricas;
    }

    /**
     * Sincroniza las existencias de una variante hacia las ubicaciones de Shopify configuradas.
     */
    protected function sincronizarStockVarianteShopify(
        ShopifyApiClient $client,
        Producto $producto,
        $ubicacionesMapeadas,
        bool $actualizarStock
    ): void {
        if (!$actualizarStock || empty($producto->shopify_inventory_item_id) || $ubicacionesMapeadas->isEmpty()) {
            return;
        }

        foreach ($ubicacionesMapeadas as $locMap) {
            try {
                $stockBodega = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $locMap->id_bodega)
                    ->value('stock') ?? 0;

                $resInv = $client->post('inventory_levels/set.json', [
                    'location_id' => $locMap->shopify_location_id,
                    'inventory_item_id' => $producto->shopify_inventory_item_id,
                    'available' => (int) $stockBodega,
                ]);
                $this->controlarRateLimit($resInv);
            } catch (\Throwable $e) {
                Log::channel('shopify_consolidacion')->warning(
                    "Error actualizando stock para producto #{$producto->id} en ubicación #{$locMap->shopify_location_id}: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Notifica el progreso a través del callback en hitos porcentuales o al finalizar.
     */
    protected function notificarProgreso(array $metricas, int $total, ?callable $onProgreso): void
    {
        if ($onProgreso && ($metricas['procesados'] % 10 === 0 || $metricas['procesados'] === $total)) {
            $porcentaje = (int) round(($metricas['procesados'] / $total) * 85) + 15;
            $onProgreso(
                min(99, $porcentaje),
                "Consolidando producto {$metricas['procesados']} de {$total} hacia Shopify...",
                $metricas
            );
        }
    }
}
