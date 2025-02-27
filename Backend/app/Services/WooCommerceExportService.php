<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WooCommerceExportService
{
    public function exportarProductos(User $user, $productos, $bodegas)
    {
        $client = new WooCommerceApiClient(
            $user->woocommerce_store_url,
            $user->woocommerce_consumer_key,
            $user->woocommerce_consumer_secret
        );

        $resultados = [
            'total' => count($productos),
            'creados' => 0,
            'actualizados' => 0,
            'errores' => 0,
            'detalles' => []
        ];

        foreach ($productos as $producto) {
            try {
                $stock = Inventario::whereIn('id_bodega', $bodegas)
                    ->where('id_producto', $producto->id)
                    ->sum('stock');


                $existente = $this->buscarProductoPorSku($client, $producto->codigo);
                Log::info("Producto existente: " . json_encode($existente));


                $productData = $this->prepararDatosProducto($producto, $stock, $client);


                if (!empty($producto->woocommerce_id)) {
                    try {
                        $response = $client->put("products/{$producto->woocommerce_id}", $productData);

                        $resultados['actualizados']++;
                        $resultados['detalles'][] = [
                            'producto_id' => $producto->id,
                            'accion' => 'actualizado',
                            'woocommerce_id' => $producto->woocommerce_id
                        ];

                        continue;
                    } catch (\Exception $e) {
                        Log::warning("Error actualizando producto por ID en WooCommerce, intentando búsqueda por SKU: " . $e->getMessage());
                    }
                }



                if ($existente) {
                    $response = $client->put("products/{$existente['id']}", $productData);

                    $producto->woocommerce_id = $existente['id'];
                    $producto->save();
                    $wooId = null;
                    if (isset($response['status']) && $response['status'] === 'success') {
                        if (isset($response['body']['id'])) {
                            $wooId = $response['body']['id'];
                        } elseif (isset($response['id'])) {
                            $wooId = $response['id'];
                        }
                    }

                    if (!$wooId) {
                        throw new \Exception("No se pudo obtener el ID del producto actualizado en WooCommerce");
                    }

                    $resultados['actualizados']++;
                    $resultados['detalles'][] = [
                        'producto_id' => $producto->id,
                        'accion' => 'actualizado',
                        'woocommerce_id' => $wooId
                    ];
                } else {
                    // Crear nuevo producto
                    $response = $client->post('products', $productData);
                    $wooId = null;
                    if (isset($response['status']) && $response['status'] === 'success') {
                        if (isset($response['body']['id'])) {
                            $wooId = $response['body']['id'];
                        } elseif (isset($response['id'])) {
                            $wooId = $response['id'];
                        }
                    }

                    if ($wooId) {
                        $producto->woocommerce_id = $wooId;
                        $producto->save();

                        $resultados['creados']++;
                        $resultados['detalles'][] = [
                            'producto_id' => $producto->id,
                            'accion' => 'creado',
                            'woocommerce_id' => $wooId
                        ];
                    } else {
                        throw new \Exception("No se pudo obtener el ID del producto creado en WooCommerce");
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error procesando producto {$producto->id}: " . $e->getMessage());
                $resultados['errores']++;
                $resultados['detalles'][] = [
                    'producto_id' => $producto->id,
                    'accion' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $resultados;
    }


    private function buscarProductoPorSku($client, $sku)
    {
        try {
            // Si el SKU está vacío, no podemos buscar
            if (empty($sku)) {
                return null;
            }

            // Buscar productos con este SKU
            $response = $client->get('products', [
                'sku' => $sku,
                'per_page' => 1
            ]);

            // Revisar si tenemos respuesta y contiene datos
            if (isset($response['body']) && is_array($response['body']) && count($response['body']) > 0) {
                return $response['body'][0];
            } elseif (is_array($response) && count($response) > 0) {
                return $response[0];
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("Error buscando producto por SKU ({$sku}): " . $e->getMessage());
            return null;
        }
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
        // $images = [];
        // if (!empty($producto->img)) {
        //     $images[] = [
        //         'src' => url('/img/' . $producto->img)
        //     ];
        // }


        $productData = [
            'name' => $producto->nombre,
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'sku' => $producto->codigo ?: $producto->barcode,
            'price' => (string)$producto->precio,
            'regular_price' => (string)$producto->precio,
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
        // if (!empty($images)) {
        //     $productData['images'] = $images;
        // }

        return $productData;
    }

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

            $response = $client->get('products/categories', [
                'search' => $categoria->nombre
            ]);

            if (
                isset($response['status']) && $response['status'] === 'success' &&
                isset($response['body']) && is_array($response['body'])
            ) {

                foreach ($response['body'] as $cat) {
                    if (strtolower($cat['name']) === strtolower($categoria->nombre)) {
                        $categoriasCache[$categoriaId] = $cat['id'];
                        return $cat['id'];
                    }
                }
            }

            $categoryData = [
                'name' => $categoria->nombre,
                'slug' => $this->generarSlug($categoria->nombre)
            ];

            $response = $client->post('products/categories', $categoryData);
            if (
                isset($response['status']) && $response['status'] === 'success' &&
                isset($response['body']) && isset($response['body']['id'])
            ) {
                $categoriasCache[$categoriaId] = $response['body']['id'];
                return $response['body']['id'];
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
