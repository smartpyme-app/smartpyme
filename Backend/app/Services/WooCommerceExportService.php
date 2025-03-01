<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceExportService
{
    public function exportarProductos(User $user, $productos, $bodega)
    {
        $client = new WooCommerceApiClient(
            $user->woocommerce_store_url,
            $user->woocommerce_consumer_key,
            $user->woocommerce_consumer_secret
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
            'errores' => 0,
            'detalles' => []
        ];

        foreach ($productos as $producto) {
            try {
                $stock = $stocks[$producto->id] ?? 0;
                $productData = $this->prepararDatosProducto($producto, $stock, $client);

                // Intentar actualizar primero si tenemos woocommerce_id
                if (!empty($producto->woocommerce_id)) {
                    if ($this->actualizarProductoExistente($client, $producto, $productData, $resultados)) {
                        continue;
                    }
                }

                // Buscar por SKU solo si no se pudo actualizar por ID
                $existente = $this->buscarProductoPorSku($client, $producto->codigo);

                if ($existente) {
                    $this->actualizarProductoPorSku($client, $producto, $existente, $productData, $resultados);
                } else {
                    $this->crearNuevoProducto($client, $producto, $productData, $resultados);
                }
            } catch (\Exception $e) {
                $this->registrarError($producto, $e, $resultados);
            }
        }

        return $resultados;
    }

    private function actualizarProductoExistente($client, $producto, $productData, &$resultados)
    {
        try {
            $client->put("products/{$producto->woocommerce_id}", $productData);
            $this->registrarExito($resultados, $producto, 'actualizado', $producto->woocommerce_id);
            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando producto por ID en WooCommerce: " . $e->getMessage());
            return false;
        }
    }

    private function actualizarProductoPorSku($client, $producto, $existente, $productData, &$resultados)
    {
        $response = $client->put("products/{$existente['id']}", $productData);
        $wooId = $this->extraerWooId($response);

        if (!$wooId) {
            throw new \Exception("No se pudo obtener el ID del producto actualizado en WooCommerce");
        }

        $producto->woocommerce_id = $existente['id'];
        $producto->save();

        $this->registrarExito($resultados, $producto, 'actualizado', $wooId);
    }

    private function crearNuevoProducto($client, $producto, $productData, &$resultados)
    {
        $response = $client->post('products', $productData);
        $wooId = $this->extraerWooId($response);

        if (!$wooId) {
            throw new \Exception("No se pudo obtener el ID del producto creado en WooCommerce");
        }

        $producto->woocommerce_id = $wooId;
        $producto->save();

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
