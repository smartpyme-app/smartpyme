<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyExportService
{
    public function exportarProductos(User $user, $productos, $bodega)
    {
        $client = new ShopifyApiClient(
            $user->empresa->shopify_store_url,
            $user->empresa->shopify_consumer_secret
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

                // Intentar actualizar primero si tenemos shopify_product_id
                if (!empty($producto->shopify_product_id)) {
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
            // Actualizar producto en Shopify
            $response = $client->put("products/{$producto->shopify_product_id}.json", [
                'product' => $productData
            ]);

            // Actualizar inventario
            $this->actualizarInventarioShopify($client, $producto, $productData['variants'][0]['inventory_quantity']);

            $this->registrarExito($resultados, $producto, 'actualizado', $producto->shopify_product_id);
            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando producto por ID en Shopify: " . $e->getMessage());
            return false;
        }
    }

    private function actualizarProductoPorSku($client, $producto, $existente, $productData, &$resultados)
    {
        // Actualizar producto
        $response = $client->put("products/{$existente['product_id']}.json", [
            'product' => $productData
        ]);

        // Guardar IDs para futuras actualizaciones
        $producto->shopify_product_id = $existente['product_id'];
        $producto->shopify_variant_id = $existente['variant_id'];
        $producto->shopify_inventory_item_id = $existente['inventory_item_id'];
        $producto->save();

        // Actualizar inventario
        $this->actualizarInventarioShopify($client, $producto, $productData['variants'][0]['inventory_quantity']);

        $this->registrarExito($resultados, $producto, 'actualizado', $existente['product_id']);
    }

    private function crearNuevoProducto($client, $producto, $productData, &$resultados)
    {
        $response = $client->post('products.json', [
            'product' => $productData
        ]);

        if (!isset($response['body']['product'])) {
            throw new \Exception("No se pudo obtener el ID del producto creado en Shopify");
        }

        $shopifyProduct = $response['body']['product'];
        $variant = $shopifyProduct['variants'][0];

        // Guardar IDs
        $producto->shopify_product_id = $shopifyProduct['id'];
        $producto->shopify_variant_id = $variant['id'];
        $producto->shopify_inventory_item_id = $variant['inventory_item_id'];
        $producto->save();

        // Actualizar inventario
        $this->actualizarInventarioShopify($client, $producto, $productData['variants'][0]['inventory_quantity']);

        $this->registrarExito($resultados, $producto, 'creado', $shopifyProduct['id']);
    }

    private function actualizarInventarioShopify($client, $producto, $stock)
    {
        try {
            // Obtener ubicación por defecto
            $locationId = $this->getDefaultLocationId($client);

            if ($producto->shopify_inventory_item_id && $locationId) {
                $client->post('inventory_levels/set.json', [
                    'location_id' => $locationId,
                    'inventory_item_id' => $producto->shopify_inventory_item_id,
                    'available' => $stock
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Error actualizando inventario en Shopify: " . $e->getMessage());
        }
    }

    private function getDefaultLocationId($client)
    {
        static $locationId = null;

        if (!$locationId) {
            try {
                $response = $client->get('locations.json');
                if (isset($response['body']['locations'][0])) {
                    $locationId = $response['body']['locations'][0]['id'];
                }
            } catch (\Exception $e) {
                Log::error("Error obteniendo ubicaciones de Shopify: " . $e->getMessage());
            }
        }

        return $locationId;
    }

    private function registrarExito(&$resultados, $producto, $accion, $shopifyId)
    {
        $resultados[$accion . 's']++;
        $resultados['detalles'][] = [
            'producto_id' => $producto->id,
            'accion' => $accion,
            'shopify_id' => $shopifyId
        ];
    }

    private function registrarError($producto, $e, &$resultados)
    {
        Log::error("Error procesando producto Shopify {$producto->id}: " . $e->getMessage());
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
            if (empty($sku)) {
                return null;
            }

            // Buscar productos que contengan este SKU
            $response = $client->get('products.json', [
                'limit' => 250,
                'fields' => 'id,variants'
            ]);

            if (isset($response['body']['products'])) {
                foreach ($response['body']['products'] as $product) {
                    foreach ($product['variants'] as $variant) {
                        if ($variant['sku'] === $sku) {
                            return [
                                'product_id' => $product['id'],
                                'variant_id' => $variant['id'],
                                'inventory_item_id' => $variant['inventory_item_id']
                            ];
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("Error buscando producto por SKU en Shopify ({$sku}): " . $e->getMessage());
            return null;
        }
    }

    private function prepararDatosProducto($producto, $stock, $client)
    {
        $images = [];
        if (!empty($producto->imagenes)) {
            foreach ($producto->imagenes as $imagen) {
                $images[] = [
                    'src' => url('/img' . $imagen->img)
                ];
            }
        }

        return [
            'title' => $producto->nombre,
            'body_html' => $producto->descripcion ?? '',
            'vendor' => 'Mi Tienda',
            'product_type' => $this->obtenerCategoria($producto->id_categoria),
            'status' => 'active',
            'tags' => $producto->tags ?? '',
            'variants' => [
                [
                    'sku' => $producto->codigo,
                    'price' => $producto->precio,
                    'compare_at_price' => $producto->precio_comparacion ?? null,
                    'inventory_quantity' => $stock,
                    'inventory_management' => 'shopify',
                    'inventory_policy' => 'deny',
                    'weight' => $producto->peso ?? 0,
                    'weight_unit' => 'g'
                ]
            ],
            'images' => $images
        ];
    }

    private function obtenerCategoria($categoriaId)
    {
        if (!$categoriaId) {
            return 'General';
        }

        $categoria = Categoria::find($categoriaId);
        return $categoria ? $categoria->nombre : 'General';
    }
}