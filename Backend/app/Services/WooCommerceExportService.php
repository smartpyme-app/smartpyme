<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceExportService
{
    /** @var WooCommerceSkuResolver */
    protected $skuResolver;

    /** @var WooCommerceResolvedProductWriter */
    protected $resolvedProductWriter;

    /** @var WooCommerceSpExportCatalogService */
    protected $catalog;

    /** @var WooCommerceTallaNombreParser */
    protected $tallaParser;

    /** @var WooCommerceProductLineMatcher */
    protected $lineMatcher;

    public function __construct(
        WooCommerceSkuResolver $skuResolver,
        WooCommerceResolvedProductWriter $resolvedProductWriter,
        WooCommerceSpExportCatalogService $catalog,
        WooCommerceTallaNombreParser $tallaParser,
        WooCommerceProductLineMatcher $lineMatcher
    ) {
        $this->skuResolver = $skuResolver;
        $this->resolvedProductWriter = $resolvedProductWriter;
        $this->catalog = $catalog;
        $this->tallaParser = $tallaParser;
        $this->lineMatcher = $lineMatcher;
    }

    public function exportarProductos(User $user, $productos, $bodega)
    {
        // Obtener la empresa del usuario para acceder a la configuración de WooCommerce
        $empresa = $user->empresa;
        
        if (!$empresa) {
            throw new \Exception("El usuario no tiene una empresa asociada");
        }
        
        if (empty($empresa->woocommerce_store_url) || 
            empty($empresa->woocommerce_consumer_key) || 
            empty($empresa->woocommerce_consumer_secret)) {
            throw new \Exception("La configuración de WooCommerce no está completa en la empresa");
        }

        if (!$empresa->woocommerceSyncPushesToRemote()) {
            throw new \Exception('El modo de sincronización actual no permite enviar productos a WooCommerce.');
        }
        
        $client = new WooCommerceApiClient(
            $empresa->woocommerce_store_url,
            $empresa->woocommerce_consumer_key,
            $empresa->woocommerce_consumer_secret
        );

        // Precalcular stocks para todos los productos de una vez
        $stocks = Inventario::whereIn('id_producto', $productos->pluck('id'))
            ->where('id_bodega', $bodega)
            ->select('id_producto', DB::raw('SUM(stock) as total_stock'))
            ->groupBy('id_producto')
            ->pluck('total_stock', 'id_producto')
            ->toArray();

        $resultados = [
            'total' => count($productos),
            'creados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'errores' => 0,
            'detalles' => []
        ];

        $groups = $this->catalog->groupForExport($productos);
        foreach ($groups as $group) {
            try {
                if ($group['variable']) {
                    $this->exportarGrupoVariable($client, $group['items'], $group['sku'], $stocks, $resultados);
                } else {
                    foreach ($group['items'] as $producto) {
                        $this->exportarProductoSimple($client, $producto, $stocks, $resultados);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error exportando grupo WooCommerce SKU ' . $group['sku'] . ': ' . $e->getMessage());
                foreach ($group['items'] as $producto) {
                    $this->registrarError($producto, $e, $resultados);
                }
            }
        }

        return $resultados;
    }

    private function exportarProductoSimple($client, Producto $producto, array $stocks, array &$resultados): void
    {
        try {
            $stock = $stocks[$producto->id] ?? 0;
            $productData = $this->prepararDatosProducto($producto, $stock, $client);
            $esImportadoWooCommerce = !empty($producto->imported_from_woocommerce_csv);

            if (!empty($producto->woocommerce_id) && empty($producto->woocommerce_parent_id)) {
                if ($this->actualizarProductoExistente($client, $producto, $productData, $resultados)) {
                    return;
                }
            }

            foreach ($this->lineMatcher->remoteSkusToTry($producto) as $skuTry) {
                $resolution = $this->skuResolver->resolveBySku($client, $skuTry);
                if ($resolution === null) {
                    continue;
                }
                try {
                    $productData['sku'] = $skuTry;
                    $this->resolvedProductWriter->applyResolution($client, $producto, $productData, $resolution);
                    $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
                } catch (\Exception $e) {
                    $this->registrarError($producto, $e, $resultados);
                }

                return;
            }

            if ($esImportadoWooCommerce) {
                $resultados['omitidos'] = ($resultados['omitidos'] ?? 0) + 1;

                return;
            }

            $this->crearNuevoProducto($client, $producto, $productData, $resultados);
        } catch (\Exception $e) {
            $this->registrarError($producto, $e, $resultados);
        }
    }

    /**
     * @param Collection<int, Producto> $items
     */
    private function exportarGrupoVariable($client, Collection $items, string $sku, array $stocks, array &$resultados): void
    {
        $parentName = $this->catalog->nombrePadreParaGrupo($items, $sku);
        $variantRows = [];
        foreach ($items as $producto) {
            $parsed = $this->tallaParser->parse((string) $producto->nombre);
            if ($parsed['talla'] === null) {
                $this->exportarProductoSimple($client, $producto, $stocks, $resultados);
                continue;
            }
            $variantRows[] = [
                'producto' => $producto,
                'talla' => $parsed['talla'],
                'variation_sku' => $this->lineMatcher->buildVariationSku($sku, $parsed['talla']),
            ];
        }

        if (count($variantRows) < 2) {
            foreach ($items as $producto) {
                $this->exportarProductoSimple($client, $producto, $stocks, $resultados);
            }

            return;
        }

        $parentWooId = $this->resolveParentWooId($items);
        $tallas = array_values(array_unique(array_column($variantRows, 'talla')));
        $first = $items->first();
        $parentPayload = $this->prepararDatosProductoPadre($first, $parentName, $tallas, $client);

        if ($parentWooId) {
            unset($parentPayload['sku']);
            $client->put("products/{$parentWooId}", $parentPayload);
        } else {
            $parentPayload['type'] = 'variable';
            unset(
                $parentPayload['sku'],
                $parentPayload['regular_price'],
                $parentPayload['price'],
                $parentPayload['manage_stock'],
                $parentPayload['stock_quantity'],
                $parentPayload['stock_status']
            );
            $response = $client->post('products', $parentPayload);
            $parentWooId = $this->extraerWooId($response);
            if (!$parentWooId) {
                throw new \Exception('No se pudo crear el producto variable padre en WooCommerce');
            }
            $this->registrarExito($resultados, $first, 'creado', $parentWooId);
        }

        foreach ($variantRows as $row) {
            /** @var Producto $producto */
            $producto = $row['producto'];
            $stock = $stocks[$producto->id] ?? 0;
            $productData = $this->prepararDatosProducto($producto, $stock, $client);
            $productData['sku'] = $row['variation_sku'];
            $variationPayload = $this->resolvedProductWriter->buildVariationPayload($productData);
            $variationPayload['attributes'] = [
                ['name' => 'Talla', 'option' => $row['talla']],
            ];

            if (!empty($producto->woocommerce_id) && (int) $producto->woocommerce_parent_id === (int) $parentWooId) {
                $client->put("products/{$parentWooId}/variations/{$producto->woocommerce_id}", $variationPayload);
                $producto->last_woocommerce_sync = now();
                $producto->saveQuietly();
                $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
                continue;
            }

            $resolution = $this->skuResolver->resolveBySku($client, $row['variation_sku']);
            if ($resolution !== null && ($resolution['mode'] ?? '') === 'variation') {
                $this->resolvedProductWriter->applyResolution($client, $producto, $productData, $resolution);
                $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
                continue;
            }

            $response = $client->post("products/{$parentWooId}/variations", $variationPayload);
            $variationId = $this->extraerWooId($response);
            if (!$variationId) {
                throw new \Exception('No se pudo crear variación en WooCommerce para talla ' . $row['talla']);
            }
            $producto->woocommerce_id = $variationId;
            $producto->woocommerce_parent_id = $parentWooId;
            $producto->last_woocommerce_sync = now();
            $producto->saveQuietly();
            $this->registrarExito($resultados, $producto, 'creado', $variationId);
        }
    }

    /**
     * @param Collection<int, Producto> $items
     */
    private function resolveParentWooId(Collection $items): ?int
    {
        foreach ($items as $p) {
            if (!empty($p->woocommerce_parent_id)) {
                return (int) $p->woocommerce_parent_id;
            }
        }
        foreach ($items as $p) {
            if (empty($p->woocommerce_id)) {
                continue;
            }
            $parentCandidate = (int) $p->woocommerce_id;
            foreach ($items as $other) {
                if ((int) ($other->woocommerce_parent_id ?? 0) === $parentCandidate) {
                    return $parentCandidate;
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $tallas
     */
    private function prepararDatosProductoPadre(Producto $producto, string $parentName, array $tallas, $client): array
    {
        $base = $this->prepararDatosProducto($producto, 0, $client);
        $base['name'] = $parentName;
        $base['attributes'] = [[
            'name' => 'Talla',
            'visible' => true,
            'variation' => true,
            'options' => $tallas,
        ]];

        return $base;
    }

    private function actualizarProductoExistente($client, $producto, $productData, &$resultados)
    {
        try {
            // Variaciones usan endpoint distinto: products/{parent_id}/variations/{variation_id}
            if (!empty($producto->woocommerce_parent_id)) {
                $endpoint = "products/{$producto->woocommerce_parent_id}/variations/{$producto->woocommerce_id}";
                $productDataVariation = $this->resolvedProductWriter->buildVariationPayload($productData);
                $client->put($endpoint, $productDataVariation);
            } else {
                $client->put("products/{$producto->woocommerce_id}", $productData);
            }

            $producto->last_woocommerce_sync = now();
            $producto->saveQuietly();

            $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando producto por ID en WooCommerce: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'woocommerce_id' => $producto->woocommerce_id,
                'woocommerce_parent_id' => $producto->woocommerce_parent_id ?? null
            ]);
            return false;
        }
    }

    private function crearNuevoProducto($client, $producto, $productData, &$resultados)
    {
        $payload = array_merge($productData, ['type' => 'simple']);
        $response = $client->post('products', $payload);
        $wooId = $this->extraerWooId($response);

        if (!$wooId) {
            throw new \Exception("No se pudo obtener el ID del producto creado en WooCommerce");
        }

        $producto->woocommerce_id = $wooId;
        $producto->woocommerce_parent_id = null;
        $producto->saveQuietly();

        $this->registrarExito($resultados, $producto, 'creado', $wooId);
    }

    private function extraerWooId($response)
    {
        if (!isset($response['status']) || $response['status'] !== 'success') {
            return null;
        }

        return $response['body']['id'] ?? $response['id'] ?? null;
    }

    private function registrarExito(&$resultados, $producto, $accion, $wooId)
    {
        $resultados[$accion . 's']++;
        $resultados['detalles'][] = [
            'producto_id' => $producto->id,
            'accion' => $accion,
            'woocommerce_id' => $wooId
        ];
    }

    private function registrarError($producto, $e, &$resultados)
    {
        Log::error("Error procesando producto {$producto->id}: " . $e->getMessage());
        $resultados['errores']++;
        $resultados['detalles'][] = [
            'producto_id' => $producto->id,
            'accion' => 'error',
            'error' => $e->getMessage()
        ];
    }

    private function prepararDatosProducto($producto, $stock, $client)
    {
        $categories = [];
        if (!empty($producto->id_categoria)) {
            $categories[] = [
                'id' => $this->obtenerCategoria($producto->id_categoria, $client)
            ];
        }

        $images = [];
        $imageStorage = app(ProductImageStorage::class);
        if (!empty($producto->img)) {
            $publicUrl = $imageStorage->url($producto->img);
            if ($publicUrl) {
                $images[] = ['src' => $publicUrl];
            }
        }

        // Calcular precio con IVA si está habilitado
        $precio = $producto->precio;
        $empresa = $producto->empresa;
        
        
        if ($empresa && $empresa->cobra_iva === 'Si' && !empty($empresa->iva) && $empresa->iva > 0) {
            $ivaDecimal = $empresa->iva / 100;
            $precio = $producto->precio * (1 + $ivaDecimal);
        }
        
        // Formatear el precio correctamente para WooCommerce
        $precio = number_format($precio, 2, '.', '');

        $nombreWoo = trim((string) ($producto->nombre_comercial ?? ''));
        if ($nombreWoo === '') {
            $parsed = $this->tallaParser->parse((string) $producto->nombre);
            $nombreWoo = $parsed['base'] !== '' ? $parsed['base'] : $producto->nombre;
        }

        $productData = [
            'name' => $nombreWoo,
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'sku' => $producto->codigo ?: $producto->barcode,
            'price' => $precio,
            'regular_price' => $precio,
            'manage_stock' => true,
            'stock_quantity' => $stock,
            'stock_status' => $stock > 0 ? 'instock' : 'outofstock'
        ];

        // Añadir descripción si existe
        if (!empty($producto->descripcion)) {
            $productData['description'] = $producto->descripcion;
            $productData['short_description'] = substr($producto->descripcion, 0, 150);
        }

        // Añadir categorías si existen
        if (!empty($categories)) {
            $productData['categories'] = $categories;
        }

        // Añadir imágenes si existen
        if (!empty($images)) {
            $productData['images'] = $images;
        }

        return WooCommerceCogsHelper::mergeCostIntoProductData($productData, (float) $producto->costo);
    }


    public function precargarCategorias($client)
    {
        static $categoriasWoo = null;

        if ($categoriasWoo !== null) {
            return $categoriasWoo;
        }

        try {
            // Cargar todas las categorías de WooCommerce de una sola vez (con paginación si hay muchas)
            $categoriasWoo = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = $client->get('products/categories', [
                    'per_page' => $perPage,
                    'page' => $page
                ]);

                if (
                    !isset($response['status']) || $response['status'] !== 'success' ||
                    !isset($response['body']) || !is_array($response['body'])
                ) {
                    break;
                }

                $categorias = $response['body'];
                foreach ($categorias as $cat) {
                    $categoriasWoo[strtolower($cat['name'])] = $cat['id'];
                }

                $page++;
            } while (count($categorias) === $perPage);

            return $categoriasWoo;
        } catch (\Exception $e) {
            Log::error("Error al precargar categorías: " . $e->getMessage());
            return [];
        }
    }

    // private function obtenerCategoria($categoriaId, $client = null)
    // {
    //     static $categoriasCache = [];

    //     if (isset($categoriasCache[$categoriaId])) {
    //         return $categoriasCache[$categoriaId];
    //     }

    //     try {
    //         $categoria = Categoria::find($categoriaId);

    //         if (!$categoria) {
    //             return 9;
    //         }

    //         $response = $client->get('products/categories', [
    //             'search' => $categoria->nombre
    //         ]);

    //         if (
    //             isset($response['status']) && $response['status'] === 'success' &&
    //             isset($response['body']) && is_array($response['body'])
    //         ) {

    //             foreach ($response['body'] as $cat) {
    //                 if (strtolower($cat['name']) === strtolower($categoria->nombre)) {
    //                     $categoriasCache[$categoriaId] = $cat['id'];
    //                     return $cat['id'];
    //                 }
    //             }
    //         }

    //         $categoryData = [
    //             'name' => $categoria->nombre,
    //             'slug' => $this->generarSlug($categoria->nombre)
    //         ];

    //         $response = $client->post('products/categories', $categoryData);
    //         if (
    //             isset($response['status']) && $response['status'] === 'success' &&
    //             isset($response['body']) && isset($response['body']['id'])
    //         ) {
    //             $categoriasCache[$categoriaId] = $response['body']['id'];
    //             return $response['body']['id'];
    //         }

    //         return 9;
    //     } catch (\Exception $e) {
    //         Log::error("Error al obtener/crear categoría en WooCommerce: " . $e->getMessage());
    //         return 9;
    //     }
    // }


    private function obtenerCategoria($categoriaId, $client = null)
    {
        static $categoriasCache = [];

        if (isset($categoriasCache[$categoriaId])) {
            return $categoriasCache[$categoriaId];
        }

        try {
            $categoria = Categoria::find($categoriaId);

            if (!$categoria) {
                return 9;
            }

            // Usar las categorías precargadas
            $categoriasWoo = $this->precargarCategorias($client);

            // Buscar en las categorías precargadas
            if (isset($categoriasWoo[strtolower($categoria->nombre)])) {
                $wooId = $categoriasWoo[strtolower($categoria->nombre)];
                $categoriasCache[$categoriaId] = $wooId;
                return $wooId;
            }

            // Si no existe, crear la categoría
            $categoryData = [
                'name' => $categoria->nombre,
                'slug' => $this->generarSlug($categoria->nombre)
            ];

            $response = $client->post('products/categories', $categoryData);
            if (
                isset($response['status']) && $response['status'] === 'success' &&
                isset($response['body']) && isset($response['body']['id'])
            ) {

                $wooId = $response['body']['id'];
                $categoriasCache[$categoriaId] = $wooId;

                // Actualizar también la lista de categorías precargadas
                $categoriasWoo[strtolower($categoria->nombre)] = $wooId;

                return $wooId;
            }

            return 9;
        } catch (\Exception $e) {
            Log::error("Error al obtener/crear categoría en WooCommerce: " . $e->getMessage());
            return 9;
        }
    }

    private function generarSlug($nombre)
    {
        $slug = strtolower($nombre);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}