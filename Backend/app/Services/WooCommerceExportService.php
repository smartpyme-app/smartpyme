<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceExportService
{
    /** @var WooCommerceSkuResolver */
    protected $skuResolver;

    /** @var WooCommerceResolvedProductWriter */
    protected $resolvedProductWriter;

    public function __construct(WooCommerceSkuResolver $skuResolver, WooCommerceResolvedProductWriter $resolvedProductWriter)
    {
        $this->skuResolver = $skuResolver;
        $this->resolvedProductWriter = $resolvedProductWriter;
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

        foreach ($productos as $producto) {
            try {
                $stock = $stocks[$producto->id] ?? 0;
                $productData = $this->prepararDatosProducto($producto, $stock, $client);

                $esImportadoWooCommerce = !empty($producto->imported_from_woocommerce_csv);

                // Intentar actualizar primero si tenemos woocommerce_id
                if (!empty($producto->woocommerce_id)) {
                    if ($this->actualizarProductoExistente($client, $producto, $productData, $resultados)) {
                        continue;
                    }
                }

                $resolution = $this->skuResolver->resolveBySku($client, (string) $producto->codigo);

                if ($resolution !== null) {
                    try {
                        $this->resolvedProductWriter->applyResolution($client, $producto, $productData, $resolution);
                        $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
                    } catch (\Exception $e) {
                        Log::warning('Error aplicando resolución SKU en export WooCommerce: ' . $e->getMessage(), [
                            'producto_id' => $producto->id,
                            'sku' => $producto->codigo,
                        ]);
                        $this->registrarError($producto, $e, $resultados);
                    }
                    continue;
                }

                // Productos importados desde CSV de WooCommerce: NUNCA crear, solo actualizar
                if ($esImportadoWooCommerce) {
                    Log::info("Producto importado de WooCommerce sin woocommerce_id o actualización fallida - omitido", [
                        'producto_id' => $producto->id,
                        'nombre' => $producto->nombre,
                        'woocommerce_id' => $producto->woocommerce_id
                    ]);
                    $resultados['omitidos'] = ($resultados['omitidos'] ?? 0) + 1;
                    continue;
                }

                $this->crearNuevoProducto($client, $producto, $productData, $resultados);
            } catch (\Exception $e) {
                $this->registrarError($producto, $e, $resultados);
            }
        }

        return $resultados;
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

        // // Preparar imagen si existe
        $images = [];
        if (!empty($producto->img)) {
            $images[] = [
                'src' => url('/img/' . $producto->img)
            ];
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

        $productData = [
            'name' => $producto->nombre,
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

        return $productData;
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