<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WooCommerceStockService
{

    public function actualizarStockEnWooCommerce($productoId, $userId)
    {
        try {

            $usuario = User::find($userId);

            $empresa = Empresa::where('id', $usuario->id_empresa)->first();

            if (!$usuario || empty($empresa->woocommerce_api_key)) {
                Log::error("Usuario no encontrado o sin API key de WooCommerce", ['user_id' => $userId]);
                return false;
            }


            if (
                empty($empresa->woocommerce_store_url) ||
                empty($empresa->woocommerce_consumer_key) ||
                empty($empresa->woocommerce_consumer_secret)
            ) {

                Log::error("Empresa no encontrada o sin credenciales completas de WooCommerce", ['empresa_id' => $empresa->id]);
                return false;
            }


            $producto = Producto::with(['imagenes', 'empresa'])->find($productoId);

            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }

            if (empty($producto->codigo)) {
                Log::warning("Producto sin código/SKU para buscar en WooCommerce", [
                    'producto_id' => $productoId,
                    'nombre' => $producto->nombre
                ]);
                return false;
            }

            $sku = $producto->codigo;
            //  $bodegas = Bodega::where('id_sucursal', $usuario->id_sucursal)->get();

            $stock = Inventario::where('id_producto', $productoId)
                ->where('id_bodega', $usuario->id_bodega)
                ->value('stock');

            if ($stock === null) {
                Log::warning("No se encontró inventario para el producto", ['producto_id' => $productoId]);
                $stock = 0;
            }

            $wooClient = new WooCommerceApiClient(
                $empresa->woocommerce_store_url,
                $empresa->woocommerce_consumer_key,
                $empresa->woocommerce_consumer_secret
            );

            $productData = $this->prepararDatosProducto($producto, $stock, $wooClient);

            if (!empty($producto->woocommerce_id)) {
                if ($this->actualizarProductoExistente($wooClient, $producto, $productData)) {
                    return true;
                }
            }

            $existente = $this->buscarProductoPorSku($wooClient, $producto->codigo);

            if ($existente) {
                $this->actualizarProductoPorSku($wooClient, $producto, $existente, $productData);
            } else {
                $this->crearNuevoProducto($wooClient, $producto, $productData);
            }


            $searchResponse = $wooClient->get('products', [
                'sku' => $sku,
                'per_page' => 1
            ]);

            Log::info("Respuesta de WooCommerce: ", $searchResponse);


            if ($searchResponse['status'] !== 'success' || count($searchResponse['body']) === 0) {
                Log::warning("No se encontró producto en WooCommerce con SKU: {$sku}", [
                    'response' => $searchResponse
                ]);
                return false;
            }

            //$wooProductId = $searchResponse[0]['id'];

            $wooProductId = $searchResponse['body'][0]['id'];

            $response = $wooClient->put('products/' . $wooProductId, [
                'stock_quantity' => (int)$stock,
                'manage_stock' => true
            ]);

            Log::info("Stock actualizado en WooCommerce", [
                'producto_id' => $productoId,
                'woocommerce_id' => $wooProductId,
                'sku' => $sku,
                'stock' => $stock,
                'response' => $response
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Error al actualizar stock en WooCommerce: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    private function actualizarProductoPorSku($client, $producto, $existente, $productData)
    {
        $response = $client->put("products/{$existente['id']}", $productData);
        $wooId = $this->extraerWooId($response);

        if (!$wooId) {
            throw new \Exception("No se pudo obtener el ID del producto actualizado en WooCommerce");
        }

        $producto->woocommerce_id = $existente['id'];
        $producto->save();
    }

    private function crearNuevoProducto($client, $producto, $productData)
    {
        $response = $client->post('products', $productData);
        $wooId = $this->extraerWooId($response);

        if (!$wooId) {
            throw new \Exception("No se pudo obtener el ID del producto creado en WooCommerce");
        }

        $producto->woocommerce_id = $wooId;
        $producto->save();
    }

    private function extraerWooId($response)
    {
        if (!isset($response['status']) || $response['status'] !== 'success') {
            return null;
        }

        return $response['body']['id'] ?? $response['id'] ?? null;
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

    private function actualizarProductoExistente($client, $producto, $productData)
    {
        try {
            if (!empty($producto->woocommerce_parent_id)) {
                $endpoint = "products/{$producto->woocommerce_parent_id}/variations/{$producto->woocommerce_id}";
                $variationData = array_filter([
                    'sku' => $productData['sku'] ?? null,
                    'regular_price' => $productData['regular_price'] ?? null,
                    'price' => $productData['price'] ?? null,
                    'manage_stock' => $productData['manage_stock'] ?? true,
                    'stock_quantity' => $productData['stock_quantity'] ?? 0,
                    'stock_status' => $productData['stock_status'] ?? 'outofstock',
                ], fn($v) => $v !== null);
                $client->put($endpoint, $variationData);
            } else {
                $client->put("products/{$producto->woocommerce_id}", $productData);
            }
            $producto->last_woocommerce_sync = now();
            $producto->saveQuietly();
            return true;
        } catch (\Exception $e) {
            Log::warning("Error actualizando producto por ID en WooCommerce: " . $e->getMessage());
            return false;
        }
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
            $exito = $this->actualizarStockEnWooCommerce($productoId, $userId);

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

    private function prepararDatosProducto($producto, $stock, $client)
    {
        $categories = [];
        if (!empty($producto->id_categoria)) {
            $categories[] = [
                'id' => $this->obtenerCategoria($producto->id_categoria, $client)
            ];
        }
        $images = [];
        if (!empty($producto->imagenes)) {
            foreach ($producto->imagenes as $imagen) {
                $images[] = [
                    'src' => url('/img' . $imagen->img)
                ];
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

        $productData = [
            'name' => $producto->nombre,
            'type' => 'simple',
            'status' => 'publish',
            'featured' => false,
            'catalog_visibility' => 'visible',
            'sku' => $producto->codigo,
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


    private function generarSlug($nombre)
    {
        $slug = strtolower($nombre);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}