<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyStockService
{
   
    public function actualizarSoloStockEnShopify($productoId, $userId)
    {
        // return true;
        try {
            $producto = Producto::find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado para actualizar stock", ['producto_id' => $productoId]);
                return false;
            }



            $usuario = User::find($userId);
            $empresa = Empresa::where('id', $usuario->id_empresa)->first();

            if (!$usuario || empty($empresa->shopify_consumer_secret) || empty($empresa->shopify_store_url)) {
                Log::error("Usuario/empresa sin configuración Shopify", ['user_id' => $userId]);
                return false;
            }


            if (empty($producto->shopify_variant_id) || empty($producto->shopify_inventory_item_id)) {
                Log::info("Producto sin IDs de Shopify - no se actualiza stock", [
                    'producto_id' => $productoId,
                    'variant_id' => $producto->shopify_variant_id,
                    'inventory_item_id' => $producto->shopify_inventory_item_id
                ]);
                return false;
            }

            $stock = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $usuario->id_bodega)
                ->value('stock');

            if ($stock === null) {
                $stock = 0;
            }

            $shopifyClient = new ShopifyApiClient(
                $empresa->shopify_store_url,
                $empresa->shopify_consumer_secret
            );
            return $this->actualizarSoloInventario($shopifyClient, $producto, $stock);
        } catch (\Exception $e) {
            Log::error("Error al actualizar solo stock en Shopify: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function actualizarProductoCompletoEnShopify($productoId, $userId, $bandera = false)
    {
        try {
            $producto = Producto::with('imagenes')->find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }



            $producto->last_shopify_sync = now();
            $producto->save();

            $usuario = User::find($userId);
            $empresa = Empresa::where('id', $usuario->id_empresa)->first();

            if (!$usuario || empty($empresa->shopify_consumer_secret)) {
                Log::error("Usuario no encontrado o sin consumer secret de Shopify", ['user_id' => $userId]);
                return false;
            }

            if (empty($empresa->shopify_store_url)) {
                Log::error("Empresa sin store URL de Shopify configurado", ['empresa_id' => $empresa->id]);
                return false;
            }

            // if (empty($producto->codigo)) {
            //     Log::warning("Producto sin código/SKU para buscar en Shopify", [
            //         'producto_id' => $productoId,
            //         'nombre' => $producto->nombre
            //     ]);
            //     return false;
            // }

            $stock = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $usuario->id_bodega)
                ->value('stock');

            if ($stock === null) {
                Log::warning("No se encontró inventario para el producto", ['producto_id' => $productoId]);
                $stock = 0;
            }

            $shopifyClient = new ShopifyApiClient(
                $empresa->shopify_store_url,
                $empresa->shopify_consumer_secret
            );

            $productData = $this->prepararDatosProducto($producto, $stock, $shopifyClient);





            if (!empty($producto->shopify_variant_id)) {
                if ($this->actualizarProductoPorId($shopifyClient, $producto, $productData)) {
                    return true;
                }
            }


            $existente = $this->buscarProductoPorSku($shopifyClient, $producto->codigo);

            if ($existente) {
                $this->actualizarProductoPorSku($shopifyClient, $producto, $existente, $productData);
            } else {
                $this->crearNuevoProducto($shopifyClient, $producto, $productData);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error al actualizar producto completo en Shopify: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    public function createdProductoCompletoEnShopify($productoId, $userId, $bandera = false)
    {
        try {
            $producto = Producto::with('imagenes')->find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }

            $usuario = User::find($userId);
            $empresa = Empresa::where('id', $usuario->id_empresa)->first();

            if (!$usuario || empty($empresa->shopify_consumer_secret)) {
                Log::error("Usuario no encontrado o sin consumer secret de Shopify", ['user_id' => $userId]);
                return false;
            }

            if (empty($empresa->shopify_store_url)) {
                Log::error("Empresa sin store URL de Shopify configurado", ['empresa_id' => $empresa->id]);
                return false;
            }


            $stock = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $usuario->id_bodega)
                ->value('stock');

            if ($stock === null) {
                Log::warning("No se encontró inventario para el producto", ['producto_id' => $productoId]);
                $stock = 0;
            }

            $shopifyClient = new ShopifyApiClient(
                $empresa->shopify_store_url,
                $empresa->shopify_consumer_secret
            );

            $productData = $this->prepararDatosProducto($producto, $stock, $shopifyClient);

            $this->crearNuevoProducto($shopifyClient, $producto, $productData);

            Log::info("Producto creado exitosamente en Shopify", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'shopify_product_id' => $producto->shopify_product_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Error al crear producto en Shopify: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }


    public function actualizarStockEnShopify($productoId, $userId)
    {
        //return true;
        return $this->actualizarProductoCompletoEnShopify($productoId, $userId);
    }


    private function actualizarSoloInventario($client, $producto, $stock)
    {
        try {
            $locationId = $this->getDefaultLocationId($client);

            $response = $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $producto->shopify_inventory_item_id,
                'available' => (int)$stock
            ]);

            Log::info("Solo stock actualizado en Shopify", [
                'producto_id' => $producto->id,
                'variant_id' => $producto->shopify_variant_id,
                'stock' => $stock
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando solo inventario en Shopify: " . $e->getMessage());
            return false;
        }
    }

    private function actualizarProductoPorId($client, $producto, $productData)
    {
        try {
            $productUpdate = [
                'title' => $productData['title'],
                'body_html' => $productData['body_html'],
                'vendor' => $productData['vendor'],
                'product_type' => $productData['product_type'],
                'status' => $productData['status']
            ];

            $response = $client->put("products/{$producto->shopify_product_id}.json", [
                'product' => $productUpdate
            ]);

            $variantUpdate = [
                'price' => $productData['variants'][0]['price'],
                'sku' => $productData['variants'][0]['sku']
            ];

            $client->put("variants/{$producto->shopify_variant_id}.json", [
                'variant' => $variantUpdate
            ]);

            $locationId = $this->getDefaultLocationId($client);
            $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $producto->shopify_inventory_item_id,
                'available' => (int)$productData['stock_quantity']
            ]);

            if (!empty($productData['images'])) {
                $this->actualizarImagenesProducto($client, $producto->shopify_product_id, $productData['images']);
            }

            Log::info("Producto completo actualizado en Shopify por ID", [
                'product_id' => $producto->shopify_product_id,
                'variant_id' => $producto->shopify_variant_id,
                'stock' => $productData['stock_quantity']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando producto completo por ID en Shopify: " . $e->getMessage());
            return false;
        }
    }

    private function actualizarImagenesProducto($client, $productId, $images)
    {
        try {
            $response = $client->get("products/{$productId}/images.json");
            $imagenesActuales = $response['body']['images'] ?? [];

            foreach ($imagenesActuales as $imagen) {
                $client->delete("products/{$productId}/images/{$imagen['id']}.json");
            }

            foreach ($images as $imagen) {
                $client->post("products/{$productId}/images.json", [
                    'image' => $imagen
                ]);
            }

            Log::info("Imágenes actualizadas en Shopify", ['product_id' => $productId]);
        } catch (\Exception $e) {
            Log::warning("Error actualizando imágenes en Shopify: " . $e->getMessage());
        }
    }

    private function actualizarVariantePorId($client, $producto, $productData)
    {
        return $this->actualizarSoloInventario($client, $producto, $productData['stock_quantity']);
    }

    private function buscarProductoPorSku($client, $sku)
    {
        try {
            if (empty($sku)) {
                return null;
            }

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
            Log::warning("Error buscando producto por SKU ({$sku}): " . $e->getMessage());
            return null;
        }
    }

    private function actualizarProductoPorSku($client, $producto, $existente, $productData)
    {
        try {
            $locationId = $this->getDefaultLocationId($client);

            $response = $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $existente['inventory_item_id'],
                'available' => (int)$productData['stock_quantity']
            ]);

            $producto->shopify_product_id = $existente['product_id'];
            $producto->shopify_variant_id = $existente['variant_id'];
            $producto->shopify_inventory_item_id = $existente['inventory_item_id'];
            $producto->save();

            Log::info("Producto actualizado en Shopify por SKU", [
                'sku' => $producto->codigo,
                'variant_id' => $existente['variant_id'],
                'stock' => $productData['stock_quantity']
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Error actualizando producto por SKU: " . $e->getMessage());
        }
    }

    private function crearNuevoProducto($client, $producto, $productData)
    {
        try {
            $response = $client->post('products.json', [
                'product' => $productData
            ]);

            if (!isset($response['body']['product'])) {
                throw new \Exception("No se pudo crear el producto en Shopify");
            }

            $shopifyProduct = $response['body']['product'];
            $variant = $shopifyProduct['variants'][0];

            $producto->shopify_product_id = $shopifyProduct['id'];
            $producto->shopify_variant_id = $variant['id'];
            $producto->shopify_inventory_item_id = $variant['inventory_item_id'];
            $producto->save();

            $locationId = $this->getDefaultLocationId($client);
            $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $variant['inventory_item_id'],
                'available' => (int)$productData['variants'][0]['inventory_quantity']
            ]);

            Log::info("Nuevo producto creado en Shopify", [
                'producto_id' => $producto->id,
                'shopify_product_id' => $shopifyProduct['id'],
                'variant_id' => $variant['id']
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Error creando producto en Shopify: " . $e->getMessage());
        }
    }

    private function getDefaultLocationId($client)
    {
        static $locationId = null;

        if (!$locationId) {
            $response = $client->get('locations.json');
            if (isset($response['body']['locations'][0])) {
                $locationId = $response['body']['locations'][0]['id'];
            }
        }

        return $locationId;
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
            'product_type' => '',
            'status' => 'active',
            'variants' => [
                [
                    'sku' => $producto->codigo,
                    'price' => $producto->precio,
                    'inventory_quantity' => (int)$stock,
                    'inventory_management' => 'shopify',
                    'inventory_policy' => 'deny'
                ]
            ],
            'images' => $images,
            'stock_quantity' => (int)$stock
        ];
    }

    public function sincronizarMultiplesProductos(array $productoIds, $userId)
    {
        $resultados = [
            'total' => count($productoIds),
            'exitosos' => 0,
            'fallidos' => 0,
            'detalles' => []
        ];

        foreach ($productoIds as $productoId) {
            $exito = $this->actualizarProductoCompletoEnShopify($productoId, $userId);

            if ($exito) {
                $resultados['exitosos']++;
            } else {
                $resultados['fallidos']++;
            }

            $resultados['detalles'][] = [
                'producto_id' => $productoId,
                'resultado' => $exito ? 'exitoso' : 'fallido'
            ];
        }

        return $resultados;
    }
}
