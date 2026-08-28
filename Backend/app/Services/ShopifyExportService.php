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

        // Separar variantes de Shopify (con shopify_product_id) de productos locales sueltos
        $variantesShopify = $productos->filter(function ($p) {
            return !empty($p->shopify_product_id);
        });

        $sueltos = $productos->filter(function ($p) {
            return empty($p->shopify_product_id);
        });

        // Exportar variantes agrupadas por producto padre
        foreach ($variantesShopify->groupBy('shopify_product_id') as $grupo) {
            try {
                $this->exportarGrupoVariantes($client, $grupo, $stocks, $resultados);
            } catch (\Exception $e) {
                foreach ($grupo as $producto) {
                    $this->registrarError($producto, $e, $resultados);
                }
            }
        }

        // Exportar productos locales sueltos como productos simples (comportamiento anterior)
        foreach ($sueltos as $producto) {
            try {
                $stock = $stocks[$producto->id] ?? 0;
                $productData = $this->prepararDatosProducto($producto, $stock, $client);

                if (!empty($producto->shopify_product_id)) {
                    if ($this->actualizarProductoExistente($client, $producto, $productData, $resultados)) {
                        continue;
                    }
                }

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

    /**
     * Exporta un grupo de variantes (mismo shopify_product_id) como UN solo producto
     * con options[] + variants[], reconstruyendo la estructura de opciones.
     */
    private function exportarGrupoVariantes($client, $grupo, $stocks, &$resultados)
    {
        $primera = $grupo->first();
        $payload = $this->prepararProductoAgrupado($grupo, $stocks);

        // 1. Actualizar por shopify_product_id
        if (!empty($primera->shopify_product_id)) {
            $client->put("products/{$primera->shopify_product_id}.json", [
                'product' => $payload
            ]);

            // Actualizar inventario por variante
            foreach ($grupo as $producto) {
                $stock = $stocks[$producto->id] ?? 0;
                $this->actualizarInventarioShopify($client, $producto, $stock);
            }

            $this->registrarExito($resultados, $primera, 'actualizado', $primera->shopify_product_id);
            return;
        }

        // 2. Resolver por SKU canónico (grupo sin product_id: caso raro, por robustez)
        $resolution = $this->resolverGrupoPorSku($client, $grupo);
        if ($resolution === 'conflict') {
            throw new \Exception('Conflicto de SKU en Shopify: el SKU resuelve a más de una variante');
        }
        if ($resolution !== null) {
            $client->put("products/{$resolution['product_id']}.json", [
                'product' => $payload
            ]);
            foreach ($grupo as $producto) {
                $producto->shopify_product_id = $resolution['product_id'];
                $producto->save();
            }
            $this->registrarExito($resultados, $primera, 'actualizado', $resolution['product_id']);
            return;
        }

        // 3. Crear nuevo producto agrupado
        $response = $client->post('products.json', [
            'product' => $payload
        ]);

        if (!isset($response['body']['product'])) {
            throw new \Exception("No se pudo obtener el ID del producto creado en Shopify");
        }

        $shopifyProduct = $response['body']['product'];
        $variantsRespuesta = $shopifyProduct['variants'] ?? [];

        // Vincular cada variante local con su variante remota (mapeando por SKU)
        foreach ($grupo as $producto) {
            $skuBuscado = $producto->shopify_sku ?: $producto->codigo;
            foreach ($variantsRespuesta as $variant) {
                if (($variant['sku'] ?? '') === $skuBuscado) {
                    $producto->shopify_product_id = $shopifyProduct['id'];
                    $producto->shopify_variant_id = $variant['id'];
                    $producto->shopify_inventory_item_id = $variant['inventory_item_id'] ?? null;
                    $producto->save();
                    $stock = $stocks[$producto->id] ?? 0;
                    $this->actualizarInventarioShopify($client, $producto, $stock);
                    break;
                }
            }
        }

        $this->registrarExito($resultados, $primera, 'creado', $shopifyProduct['id']);
    }

    /**
     * Resuelve el product_id común de un grupo de variantes por SKU.
     *
     * @return array|null|string { product_id } | null | 'conflict'
     */
    private function resolverGrupoPorSku($client, $grupo)
    {
        $resolver = new ShopifySkuResolver();
        $productIds = [];

        foreach ($grupo as $producto) {
            $sku = $producto->shopify_sku ?: $producto->codigo;
            if (empty($sku)) {
                continue;
            }
            $r = $resolver->resolveBySku($client, $sku);
            if ($r === 'conflict') {
                return 'conflict';
            }
            if ($r !== null && !empty($r['product_id'])) {
                $productIds[$r['product_id']] = $r;
            }
        }

        if (count($productIds) === 1) {
            return reset($productIds);
        }

        return null;
    }

    private function prepararProductoAgrupado($grupo, $stocks)
    {
        $primera = $grupo->first();

        $options = [];
        if (!empty($primera->option1_name)) {
            $options[] = ['name' => $primera->option1_name];
        }
        if (!empty($primera->option2_name)) {
            $options[] = ['name' => $primera->option2_name];
        }
        if (!empty($primera->option3_name)) {
            $options[] = ['name' => $primera->option3_name];
        }

        $variants = [];
        foreach ($grupo as $producto) {
            $variants[] = [
                'sku' => $producto->shopify_sku ?: $producto->codigo,
                'price' => $producto->precio,
                'option1' => $producto->option1_value,
                'option2' => $producto->option2_value,
                'option3' => $producto->option3_value,
                'barcode' => $producto->barcode ?? null,
                'inventory_management' => 'shopify',
                'inventory_policy' => 'deny',
                'inventory_quantity' => $stocks[$producto->id] ?? 0,
            ];
        }

        $images = [];
        if (!empty($primera->imagenes)) {
            foreach ($primera->imagenes as $imagen) {
                $images[] = ['src' => url('/img' . $imagen->img)];
            }
        }

        return [
            'title' => $primera->nombre,
            'body_html' => $primera->descripcion ?? '',
            'vendor' => 'Mi Tienda',
            'product_type' => $this->obtenerCategoria($primera->id_categoria),
            'status' => 'active',
            'options' => $options,
            'variants' => $variants,
            'images' => $images,
        ];
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
