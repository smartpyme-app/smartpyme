<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Services\ShopifyStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShopifyQueueController extends Controller
{
    /**
     * Iniciar importación por trabajos pendientes (compatible con Hostinger)
     */
    public function iniciarImportacion(Request $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();
            
            // Validar datos requeridos
            if (empty($request->shopify_store_url) || empty($request->shopify_consumer_secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL de tienda y Consumer Secret son requeridos'
                ], 400);
            }

            // Obtener productos de Shopify
            $productosShopify = $this->obtenerProductosShopify($request);
            
            if (empty($productosShopify)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron productos en Shopify'
                ], 404);
            }

            // Crear trabajos pendientes para cada producto
            $trabajosCreados = 0;
            foreach ($productosShopify as $productoShopify) {
                $this->crearTrabajoProducto($productoShopify, $usuario);
                $trabajosCreados++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Trabajos de importación creados exitosamente',
                'data' => [
                    'trabajos_creados' => $trabajosCreados,
                    'siguiente_paso' => 'Ejecutar comando: php artisan shopify:procesar-trabajos --lote=10 --procesar-productos-shopify',
                    'instrucciones' => [
                        '1. Los trabajos están guardados en la base de datos',
                        '2. Puedes procesarlos cuando quieras con el comando',
                        '3. Cada ejecución del comando procesa 10 productos',
                        '4. Repite el comando hasta completar todos los productos'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error iniciando importación Shopify", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar estado de trabajos pendientes
     */
    public function verificarEstado(Request $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();
            
            $trabajosPendientes = \App\Models\TrabajosPendientes::where('tipo', 'shopify_import_producto')
                ->where('estado', 'pendiente')
                ->count();
                
            $trabajosCompletados = \App\Models\TrabajosPendientes::where('tipo', 'shopify_import_producto')
                ->where('estado', 'completado')
                ->count();
                
            $trabajosFallidos = \App\Models\TrabajosPendientes::where('tipo', 'shopify_import_producto')
                ->where('estado', 'fallido')
                ->count();
                
            $trabajosProcesando = \App\Models\TrabajosPendientes::where('tipo', 'shopify_import_producto')
                ->where('estado', 'procesando')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'trabajos_pendientes' => $trabajosPendientes,
                    'trabajos_completados' => $trabajosCompletados,
                    'trabajos_fallidos' => $trabajosFallidos,
                    'trabajos_procesando' => $trabajosProcesando,
                    'total_trabajos' => $trabajosPendientes + $trabajosCompletados + $trabajosFallidos + $trabajosProcesando,
                    'progreso_porcentaje' => $trabajosCompletados > 0 ? round(($trabajosCompletados / ($trabajosPendientes + $trabajosCompletados + $trabajosFallidos + $trabajosProcesando)) * 100, 2) : 0
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error verificando estado de trabajos", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Continuar importación (siguiente lote)
     */
    public function continuarImportacion(Request $request)
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();
            $colaKey = "shopify_cola_empresa_{$usuario->id_empresa}";
            
            $productosRestantes = Cache::get($colaKey, []);
            
            if (empty($productosRestantes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay productos pendientes en la cola'
                ], 404);
            }

            // Procesar siguiente lote (10 productos)
            $lote = array_slice($productosRestantes, 0, 10);
            $restantes = array_slice($productosRestantes, 10);

            $resultado = $this->procesarLote($lote, $usuario);

            // Guardar productos restantes
            if (!empty($restantes)) {
                Cache::put($colaKey, $restantes, 3600);
            } else {
                Cache::forget($colaKey);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lote procesado',
                'data' => [
                    'productos_procesados' => $resultado['count'],
                    'productos_restantes' => count($restantes),
                    'siguiente_paso' => !empty($restantes) ? 'Continuar con siguiente lote' : 'Importación completada'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error continuando importación Shopify", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    private function obtenerProductosShopify($request)
    {
        try {
            // Extraer el nombre de la tienda de la URL
            $storeUrl = $request->shopify_store_url;
            $storeName = $this->extraerNombreTienda($storeUrl);
            
            if (!$storeName) {
                throw new \Exception('URL de Shopify inválida');
            }

            // Construir URL base de la API de Shopify
            $baseUrl = "https://{$storeName}.myshopify.com/admin/api/2024-10/products.json";
            
            // Obtener TODOS los productos usando paginación
            $productos = $this->obtenerTodosLosProductosDeShopify($baseUrl, $request->shopify_consumer_secret);
            
            return $productos;
        } catch (\Exception $e) {
            Log::error("Error obteniendo productos de Shopify", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function extraerNombreTienda($url)
    {
        // Extraer el nombre de la tienda de la URL
        if (preg_match('/https?:\/\/([^.]+)\.myshopify\.com/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function obtenerTodosLosProductosDeShopify($baseUrl, $consumerSecret)
    {
        $productos = [];
        $page = 1;
        $limit = 250; // Máximo permitido por Shopify
        
        do {
            $url = $baseUrl . "?limit={$limit}&page={$page}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-Shopify-Access-Token: ' . $consumerSecret,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Log::error("Error obteniendo productos de Shopify", [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                break;
            }
            
            $data = json_decode($response, true);
            if (empty($data['products'])) {
                break;
            }
            
            $productos = array_merge($productos, $data['products']);
            $page++;
            
            // Prevenir bucles infinitos
            if ($page > 100) {
                break;
            }
            
        } while (count($data['products']) === $limit);
        
        return $productos;
    }

    private function procesarLote($productos, $usuario)
    {
        $shopifyTransformer = new \App\Services\ShopifyTransformer();
        $productosImportados = 0;
        
        foreach ($productos as $productoShopify) {
            try {
                // Transformar productos usando ShopifyTransformer
                $productosTransformados = $shopifyTransformer->transformarProductoDesdeShopify(
                    $productoShopify, 
                    $usuario->id_empresa, 
                    $usuario->id, 
                    $usuario->id_sucursal,
                    true, // incluirDrafts
                    false // NO es importación masiva (es cola)
                );

                foreach ($productosTransformados as $productoData) {
                    // Crear/actualizar producto
                    $producto = $this->crearOActualizarProducto($productoData, $usuario);
                    
                    if ($producto) {
                        $this->crearInventarioProducto($producto->id, $productoData, $usuario->id_empresa, $usuario->id);
                        $productosImportados++;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error procesando producto en cola", [
                    'producto_id' => $productoShopify['id'] ?? 'N/A',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return ['count' => $productosImportados];
    }

    private function crearOActualizarProducto($productoData, $usuario)
    {
        // Implementar lógica de creación/actualización
        // Similar al ProductosController pero simplificado
        try {
            $producto = new \App\Models\Inventario\Producto();
            $producto->nombre = $productoData['nombre'];
            $producto->nombre_variante = $productoData['nombre_variante'] ?? null;
            $producto->descripcion = $productoData['descripcion'] ?? '';
            $producto->precio = $productoData['precio'] ?? 0;
            $producto->costo = $productoData['costo'] ?? 0;
            $producto->codigo = $productoData['codigo'] ?? '';
            $producto->barcode = $productoData['barcode'] ?? '';
            $producto->id_empresa = $usuario->id_empresa;
            $producto->id_categoria = $this->obtenerOCrearCategoria($productoData, $usuario->id_empresa)->id;
            $producto->tipo = 'Producto';
            $producto->enable = true;
            
            // Campos específicos de Shopify
            $producto->shopify_product_id = $productoData['shopify_product_id'] ?? null;
            $producto->shopify_variant_id = $productoData['shopify_variant_id'] ?? null;
            $producto->shopify_inventory_item_id = $productoData['shopify_inventory_item_id'] ?? null;
            $producto->last_shopify_sync = now();
            
            $producto->save();
            
            return $producto;
        } catch (\Exception $e) {
            Log::error("Error creando producto", [
                'producto_data' => $productoData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function obtenerOCrearCategoria($productoData, $idEmpresa)
    {
        // Cache key para evitar consultas repetitivas
        $cacheKey = "categoria_general_empresa_{$idEmpresa}";
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function() use ($idEmpresa) {
            $categoria = \App\Models\Inventario\Categorias\Categoria::where('nombre', 'General')
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$categoria) {
                $categoria = new \App\Models\Inventario\Categorias\Categoria();
                $categoria->nombre = 'General';
                $categoria->descripcion = 'Categoría general para productos importados';
                $categoria->enable = true;
                $categoria->id_empresa = $idEmpresa;
                $categoria->save();
            }

            return $categoria;
        });
    }

    private function crearTrabajoProducto($productoShopify, $usuario)
    {
        try {
            $trabajo = new \App\Models\TrabajosPendientes();
            $trabajo->tipo = 'shopify_import_producto';
            $trabajo->estado = 'pendiente';
            $trabajo->prioridad = 1;
            $trabajo->parametros = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_sucursal' => $usuario->id_sucursal,
                'shopify_store_url' => request('shopify_store_url'),
                'shopify_consumer_secret' => request('shopify_consumer_secret'),
                'shopify_consumer_key' => request('shopify_consumer_key')
            ]);
            $trabajo->datos = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_sucursal' => $usuario->id_sucursal,
                'shopify_store_url' => request('shopify_store_url'),
                'shopify_consumer_secret' => request('shopify_consumer_secret'),
                'shopify_consumer_key' => request('shopify_consumer_key')
            ]);
            $trabajo->intentos = 0;
            $trabajo->max_intentos = 3;
            $trabajo->fecha_creacion = now();
            $trabajo->fecha_procesamiento = null;
            $trabajo->id_usuario = $usuario->id;
            $trabajo->id_empresa = $usuario->id_empresa;
            $trabajo->save();

            Log::info("Trabajo creado para producto Shopify", [
                'trabajo_id' => $trabajo->id,
                'producto_id' => $productoShopify['id'],
                'titulo' => $productoShopify['title']
            ]);

        } catch (\Exception $e) {
            Log::error("Error creando trabajo para producto", [
                'producto_id' => $productoShopify['id'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
        }
    }

    private function crearInventarioProducto($productoId, $productoData, $idEmpresa, $idUsuario)
    {
        try {
            // Obtener la primera bodega activa de la empresa
            $bodega = \App\Models\Inventario\Bodega::where('id_empresa', $idEmpresa)
                ->where('enable', true)
                ->first();

            if (!$bodega) {
                Log::warning("No se encontró bodega activa para la empresa {$idEmpresa}");
                return;
            }

            // Crear inventario
            $inventario = new \App\Models\Inventario\Inventario();
            $inventario->id_producto = $productoId;
            $inventario->id_bodega = $bodega->id;
            $inventario->stock = $productoData['_stock'] ?? 0;
            $inventario->stock_minimo = 0;
            $inventario->stock_maximo = 1000;
            $inventario->enable = true;
            $inventario->save();

        } catch (\Exception $e) {
            Log::error("Error creando inventario", [
                'producto_id' => $productoId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
