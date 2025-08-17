<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ShopifyStockService
{
    public function actualizarStockEnShopify($productoId, $userId)
    {
        try {
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

            $producto = Producto::with('imagenes')->find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }

            if (empty($producto->codigo)) {
                Log::warning("Producto sin código/SKU para buscar en Shopify", [
                    'producto_id' => $productoId,
                    'nombre' => $producto->nombre
                ]);
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

            // Actualizar por shopify_variant_id si existe
            if (!empty($producto->shopify_variant_id)) {
                if ($this->actualizarVariantePorId($shopifyClient, $producto, $productData)) {
                    return true;
                }
            }

            // Buscar por SKU
            $existente = $this->buscarProductoPorSku($shopifyClient, $producto->codigo);

            if ($existente) {
                $this->actualizarProductoPorSku($shopifyClient, $producto, $existente, $productData);
            } else {
                $this->crearNuevoProducto($shopifyClient, $producto, $productData);
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Error al actualizar stock en Shopify: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    private function actualizarVariantePorId($client, $producto, $productData)
    {
        try {
            // Obtener la ubicación principal
            $locationId = $this->getDefaultLocationId($client);
            
            // Actualizar el nivel de inventario
            $response = $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $producto->shopify_inventory_item_id,
                'available' => (int)$productData['stock_quantity']
            ]);

            Log::info("Stock actualizado en Shopify por variant_id", [
                'variant_id' => $producto->shopify_variant_id,
                'stock' => $productData['stock_quantity']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando por variant_id en Shopify: " . $e->getMessage());
            return false;
        }
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
            Log::warning("Error buscando producto por SKU ({$sku}): " . $e->getMessage());
            return null;
        }
    }

    private function actualizarProductoPorSku($client, $producto, $existente, $productData)
    {
        try {
            $locationId = $this->getDefaultLocationId($client);

            // Actualizar inventario
            $response = $client->post('inventory_levels/set.json', [
                'location_id' => $locationId,
                'inventory_item_id' => $existente['inventory_item_id'],
                'available' => (int)$productData['stock_quantity']
            ]);

            // Guardar IDs para futuras actualizaciones
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
            // Crear producto en Shopify
            $response = $client->post('products.json', [
                'product' => $productData
            ]);

            if (!isset($response['body']['product'])) {
                throw new \Exception("No se pudo crear el producto en Shopify");
            }

            $shopifyProduct = $response['body']['product'];
            $variant = $shopifyProduct['variants'][0]; // Primera variante

            // Guardar IDs
            $producto->shopify_product_id = $shopifyProduct['id'];
            $producto->shopify_variant_id = $variant['id'];
            $producto->shopify_inventory_item_id = $variant['inventory_item_id'];
            $producto->save();

            // Actualizar inventario
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
            $exito = $this->actualizarStockEnShopify($productoId, $userId);

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