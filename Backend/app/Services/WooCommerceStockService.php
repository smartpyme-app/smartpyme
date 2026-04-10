<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WooCommerceStockService
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

            $resolution = $this->skuResolver->resolveBySku($wooClient, (string) $producto->codigo);

            if ($resolution !== null) {
                $this->resolvedProductWriter->applyResolution($wooClient, $producto, $productData, $resolution);

                return true;
            }

            if (!empty($producto->imported_from_woocommerce_csv)) {
                Log::info('Producto importado desde WooCommerce: sin coincidencia remota por SKU, no se crea en WooCommerce', [
                    'producto_id' => $producto->id,
                    'sku' => $producto->codigo,
                ]);

                return false;
            }

            $this->crearNuevoProducto($wooClient, $producto, $productData);

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

    private function crearNuevoProducto($client, $producto, $productData)
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
    }

    private function extraerWooId($response)
    {
        if (!isset($response['status']) || $response['status'] !== 'success') {
            return null;
        }

        return $response['body']['id'] ?? $response['id'] ?? null;
    }

    private function actualizarProductoExistente($client, $producto, $productData)
    {
        try {
            if (!empty($producto->woocommerce_parent_id)) {
                $endpoint = "products/{$producto->woocommerce_parent_id}/variations/{$producto->woocommerce_id}";
                $variationData = $this->resolvedProductWriter->buildVariationPayload($productData);
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