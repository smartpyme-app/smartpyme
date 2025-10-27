<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToShopify;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Imagen;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Services\ShopifyApiClient;
use Illuminate\Http\Request;
use App\Services\ShopifyTransformer;
use App\Services\ShippingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use App\Services\ShopifySyncCache;

class ShopifyController extends Controller
{
    protected $transformer;
    protected $cache;
    protected $shippingService;


    public function __construct(ShopifyTransformer $transformer, ShopifySyncCache $cache, ShippingService $shippingService)
    {
        $this->transformer = $transformer;
        $this->cache = $cache;
        $this->shippingService = $shippingService;
    }

    public function handle($tokenEmpresa, Request $request)
    {
        Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");
        Log::info("Datos del webhook: ", $request->all());

        $webhookTopic = $request->header('X-Shopify-Topic');

        // Log::info("Tipo de webhook: {$webhookTopic}");


        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        try {
            switch ($webhookTopic) {
                case 'test':
                    return $this->procesarPruebaWebhook($request, $empresa);

                case 'orders/create':
                    return $this->procesarVenta($tokenEmpresa, $request);

                case 'orders/cancelled':
                    return $this->procesarVentaCancelada($tokenEmpresa, $request);

                case 'orders/updated':
                    return $this->procesarVentaActualizada($tokenEmpresa, $request);

                case 'customers/create':
                    return $this->procesarClienteCreado($request, $empresa, $usuario);

                case 'customers/update':
                    return $this->procesarClienteActualizado($request, $empresa, $usuario);

                case 'products/create':
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                case 'products/update':
                    return $this->procesarProductoActualizado($request, $empresa, $usuario);

                default:
                    Log::warning("Tipo de webhook no manejado: {$webhookTopic}");
                    return response()->json(['message' => 'Webhook recibido pero no procesado'], 200);
            }
        } catch (\Exception $e) {
            Log::error("Error procesando webhook Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar webhook',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function procesarProductoActualizado(Request $request, $empresa, $usuario)
    {
        // Log::info("Producto desde Shopify", ['product_id' => $request->id]);

        $productosData = $this->transformer->transformarProductoDesdeShopify(
            $request->all(),
            $empresa->id,
            $usuario->id,
            $usuario->id_sucursal,
            true, // incluirDrafts
            false // NO es importación masiva (es webhook)
        );

        // Verificar si se obtuvieron productos válidos
        if (empty($productosData)) {
            Log::warning("No se pudieron transformar productos válidos desde Shopify", [
                'shopify_product_id' => $request->id
            ]);
            return response()->json(['status' => 'success', 'message' => 'No valid products to process'], 200);
        }

        $categoriaData = $this->transformer->transformarCategoriaDesdeShopify(
            $request->all(),
            $empresa->id
        );
        // Log::info("Categoria desde Shopify", ['categoria_id' => $categoriaData]);

        foreach ($productosData as $productoData) {
            $producto = $this->buscarProductoExistente($request->id, $productoData, $empresa->id);
            //Log::info("Producto existente", ['producto_id' => $producto->id]);

            // Log::info("Data producto", ['producto_id' => $productoData]);
            $categoria = $this->obtenerCategoria($request->all(), $categoriaData, $empresa->id);
            $productoData['id_categoria'] = $categoria->id;

            if ($producto) {
                if ($this->cache->isShopifyDataDifferent($producto, $productoData)) {
                    $this->cache->lockSync($producto->id);

                    $this->actualizarProductoExistente($producto, $productoData, $usuario);

                    $producto->fresh();
                    $this->cache->saveProductSnapshot($producto);

                    // Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
                } else {
                    Log::info("Producto sin cambios desde Shopify", ['producto_id' => $producto->id]);
                }
            } else {
                // Verificación adicional de duplicados antes de crear
                $duplicadoPorSKU = !empty($productoData['codigo']) ? 
                    Producto::where('codigo', $productoData['codigo'])
                        ->where('id_empresa', $empresa->id)
                        ->exists() : false;

                if ($duplicadoPorSKU) {
                    Log::warning("Intento de crear producto duplicado por SKU", [
                        'shopify_product_id' => $request->id,
                        'sku' => $productoData['codigo']
                    ]);
                    continue; // Saltar este producto
                }

                $nuevoProducto = $this->crearNuevoProducto($productoData, $usuario, $request);

                if ($nuevoProducto) {
                    $this->cache->lockSync($nuevoProducto->id);
                    $this->cache->saveProductSnapshot($nuevoProducto);
                }
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function buscarProductoExistente($shopifyId, $productoData, $empresaId)
    {
        // Búsqueda principal por IDs de Shopify
        $producto = Producto::where('shopify_product_id', $shopifyId)
            ->where('shopify_variant_id', $productoData['shopify_variant_id'])
            ->where('id_empresa', $empresaId)
            ->first();

        // Si no se encuentra, buscar por SKU como respaldo (para productos creados antes de la integración)
        if (!$producto && !empty($productoData['codigo'])) {
            $producto = Producto::where('codigo', $productoData['codigo'])
                ->where('id_empresa', $empresaId)
                ->whereNull('shopify_product_id') // Solo productos sin ID de Shopify
                ->first();
                
            // Si encontramos uno por SKU, actualizamos sus IDs de Shopify
            if ($producto) {
                $producto->update([
                    'shopify_product_id' => $shopifyId,
                    'shopify_variant_id' => $productoData['shopify_variant_id'],
                    'shopify_inventory_item_id' => $productoData['shopify_inventory_item_id'] ?? null,
                ]);
            }
        }

        return $producto;
    }

    private function obtenerCategoria($requestData, $categoriaData, $empresaId)
    {
        // Si no hay categoría en Shopify o los datos de categoría están vacíos
        if (empty($requestData['category']) || empty($categoriaData['nombre'])) {
            return $this->buscarCategoria('General', $empresaId);
        }
        
        return $this->buscarCategoria($categoriaData['nombre'], $empresaId);
    }

    private function actualizarProductoExistente($producto, $productoData, $usuario)
    {
        $stockActual = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->value('stock') ?? 0;

        // Extraer datos especiales que no van al modelo
        $stockNuevo = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal']);

        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();

        $producto->update($productoData);

        if ($stockActual != $stockNuevo) {
            $this->actualizarInventario($producto->id, $stockNuevo, $usuario->id_bodega, $idUsuario);

            $inventario = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
                ->where('id_bodega', $usuario->id_bodega)
                ->first();

            if ($inventario) {
                $this->cache->saveInventorySnapshot($inventario, $producto->id);
            }
        }

        // No procesar imágenes durante actualización de productos existentes
        // $this->procesarImagenes(request(), $producto->id);
    }


    private function crearNuevoProducto($productoData, $usuario, $request)
    {
        // Extraer datos especiales que no van al modelo
        $stock = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal']);
        
        // NO marcar syncing_from_shopify para webhooks - solo para importaciones masivas
        $productoData['last_shopify_sync'] = now();
        
        $producto = Producto::create($productoData);
        
        $this->actualizarInventario($producto->id, $stock, $usuario->id_bodega, $idUsuario);
        $this->procesarImagenes($request, $producto->id);

        $inventario = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->first();
            
        if ($inventario) {
            $this->cache->saveInventorySnapshot($inventario, $producto->id);
        }

        // Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        
        return $producto;
    }

    public function procesarImagenes($request, $productoId)
    {
        $imagenes = $request->images;
        foreach ($imagenes as $imagen) {
            $imagenData = [
                'id_producto' => $productoId,
                'src' => $imagen['src'],
                'shopify_image_id' => $imagen['id'],
            ];
            // Log::info($imagenData);
            // Log::info("Procesando imagen", ['imagen_id' => $imagen['id']]);
            $this->storeImage($imagenData);
        }
    }

    private function procesarClienteCreado(Request $request, $empresa, $usuario)
    {
        Log::info('=== PROCESANDO CLIENTE CREADO DESDE SHOPIFY ===', [
            'shopify_customer_id' => $request->id,
            'customer_email' => $request->email ?? 'N/A',
            'customer_name' => ($request->first_name ?? '') . ' ' . ($request->last_name ?? ''),
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'webhook_type' => 'customers/create'
        ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            
            Log::info('=== CLIENTE CREADO - DATOS TRANSFORMADOS ===', [
                'cliente_data' => $clienteData,
                'shopify_customer_id' => $request->id
            ]);
            
            $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
            Log::info('=== CLIENTE CREADO/ACTUALIZADO ===', [
                'cliente_id' => $cliente->id,
                'cliente_correo' => $cliente->correo,
                'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                'cliente_creado' => $cliente->wasRecentlyCreated,
                'shopify_customer_id' => $request->id,
                'webhook_type' => 'customers/create'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente procesado exitosamente',
                'cliente_id' => $cliente->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando cliente creado desde Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function procesarClienteActualizado(Request $request, $empresa, $usuario)
    {
        Log::info('=== PROCESANDO CLIENTE ACTUALIZADO DESDE SHOPIFY ===', [
            'shopify_customer_id' => $request->id,
            'customer_email' => $request->email ?? 'N/A',
            'customer_name' => ($request->first_name ?? '') . ' ' . ($request->last_name ?? ''),
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'webhook_type' => 'customers/update'
        ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            
            Log::info('=== CLIENTE ACTUALIZADO - DATOS TRANSFORMADOS ===', [
                'cliente_data' => $clienteData,
                'shopify_customer_id' => $request->id
            ]);
            
            $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
            Log::info('=== CLIENTE ACTUALIZADO ===', [
                'cliente_id' => $cliente->id,
                'cliente_correo' => $cliente->correo,
                'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                'cliente_creado' => $cliente->wasRecentlyCreated,
                'shopify_customer_id' => $request->id,
                'webhook_type' => 'customers/update'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente actualizado exitosamente',
                'cliente_id' => $cliente->id
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando cliente actualizado desde Shopify: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al actualizar cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        // Log::info("=== INICIANDO PROCESAMIENTO DE VENTA ===", [
        //    'token_empresa' => $tokenEmpresa,
        //    'shopify_order_id' => $request->id ?? 'N/A'
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        // Log::info("Empresa encontrada", ['empresa_id' => $empresa->id, 'empresa_nombre' => $empresa->nombre]);

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            Log::error("Usuario no encontrado", ['empresa_id' => $empresa->id]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }

        // Log::info("Usuario encontrado", ['usuario_id' => $usuario->id, 'usuario_nombre' => $usuario->name]);

        // Log::info("Buscando documento", [
        //     'facturacion_electronica' => $empresa->facturacion_electronica,
        //     'id_sucursal' => $usuario->id_sucursal
        // ]);

        if ($empresa->facturacion_electronica) {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                ->where('nombre', 'Factura')
                ->where('activo', true)
                ->first();
            
        } else {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)
                ->where('nombre', 'Ticket')
                ->where('activo', true)
                ->first();
        }

        if (!$documento) {
            Log::error("Ningún documento encontrado", [
                'id_sucursal' => $usuario->id_sucursal,
                'facturacion_electronica' => $empresa->facturacion_electronica
            ]);
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Ningún documento activo encontrado para la sucursal'
            ], 500);
        }

        // Log::info("Documento encontrado", ['documento_id' => $documento->id, 'documento_nombre' => $documento->nombre]);

        try {
            DB::beginTransaction();

            // Log::info("Iniciando procesamiento de venta", [
            //     'shopify_order_id' => $request->id,
            //     'usuario_id' => $usuario->id,
            //     'empresa_id' => $usuario->id_empresa,
            //     'documento_id' => $documento->id
            // ]);

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_bodega' => $usuario->id_bodega,
                'id_sucursal' => $usuario->id_sucursal,
                'id_documento' => $documento->id,
                'id_canal' => $empresa->shopify_canal_id
            ]);

            // Log::info("Datos del request después del merge", $request->all());

            $clienteData = $this->transformer->transformarCliente($request->all());
            
            Log::info('=== PROCESANDO CLIENTE EN VENTA SHOPIFY ===', [
                'shopify_order_id' => $request->id ?? 'N/A',
                'shopify_customer_id' => $request->customer['id'] ?? 'N/A',
                'customer_email' => $clienteData['correo'],
                'customer_name' => $clienteData['nombre'] . ' ' . $clienteData['apellido'],
                'empresa_id' => $usuario->id_empresa,
                'usuario_id' => $usuario->id
            ]);
            
            $cliente = $this->buscarOActualizarCliente($clienteData, $usuario->id_empresa);
            
            Log::info('=== CLIENTE PROCESADO EN VENTA ===', [
                'cliente_id' => $cliente->id,
                'cliente_correo' => $cliente->correo,
                'cliente_nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                'cliente_creado' => $cliente->wasRecentlyCreated,
                'shopify_order_id' => $request->id ?? 'N/A',
                'shopify_customer_id' => $request->customer['id'] ?? 'N/A'
            ]);

            $ventaData = $this->transformer->transformarVenta(
                $request->all(),
                $cliente->id,
                $documento->id,
                $documento->correlativo
            );
            // Log::info("Datos de la venta transformados", $ventaData);
            $venta = Venta::create($ventaData);
            
            // Log::info("Venta creada", ['venta_id' => $venta->id]);

            // Log::info($request->line_items);
            foreach ($request->line_items as $item) {
                // Validar que el item tenga los datos mínimos necesarios
                if (empty($item) || !is_array($item)) {
                    Log::warning("Line item inválido o vacío", ['item' => $item]);
                    continue;
                }

                // Log::info("Procesando line item", ['variant_id' => $item['variant_id'] ?? 'N/A', 'sku' => $item['sku'] ?? 'N/A']);
                
                $producto = null;
                
                // Buscar producto por variant_id si existe
                if (!empty($item['variant_id'])) {
                    $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                // Si no se encuentra por variant_id, buscar por SKU
                if (!$producto && !empty($item['sku'])) {
                    $producto = Producto::where('codigo', $item['sku'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                // Si no se encuentra el producto, crearlo
                if (!$producto) {
                    // Log::info("Producto no encontrado, creando nuevo producto", [
                    //     'variant_id' => $item['variant_id'] ?? 'N/A',
                    //     'sku' => $item['sku'] ?? 'N/A',
                    //     'title' => $item['title'] ?? 'N/A'
                    // ]);
                    
                    $productoData = $this->transformer->transformarProducto(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                    
                    // Log::info("Producto creado", ['producto_id' => $producto->id]);
                }

                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                // Actualizar inventario
                Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->decrement('stock', $item['quantity']);

                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();

                if ($inventario) {
                    $inventario->kardex($venta, $item['quantity'], $item['price']);
                }
            }

            // Procesar tipos de envío si existen
            if (!empty($request->shipping_lines)) {
                Log::info("Procesando tipos de envío", [
                    'venta_id' => $venta->id,
                    'shipping_lines_count' => count($request->shipping_lines)
                ]);

                $detallesEnvio = $this->shippingService->procesarTiposEnvio(
                    $request->shipping_lines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );

                Log::info("Detalles de envío procesados", [
                    'venta_id' => $venta->id,
                    'detalles_creados' => count($detallesEnvio)
                ]);
            }

            $documento = Documento::findOrfail($venta->id_documento);
            $documento->increment('correlativo');

            DB::commit();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta procesada correctamente',
                'venta_id' => $venta->id
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando venta de Shopify: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportarShopify(Request $request)
    {
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        if (
            empty($empresa->shopify_store_url) ||
            empty($empresa->shopify_consumer_secret)
        ) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'No tienes configurada la integración con Shopify'
            ], 400);
        }

        if ($empresa->shopify_status != 'connected') {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa debe estar activa con integración de Shopify'
            ], 400);
        }

        $sucursalId = $user->id_bodega;

        // Encolar el trabajo
        ExportProductsToShopify::dispatch($user->id, $sucursalId);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Exportación de productos a Shopify iniciada. Este proceso puede tomar varios minutos.'
        ]);
    }

    private function buscarCategoria($nombre, $id_empresa)
    {
        $categoria = Categoria::where('nombre', $nombre)
            ->where('id_empresa', $id_empresa)
            ->first();

        if (!$categoria) {
            $categoria = Categoria::create([
                'nombre' => $nombre,
                'id_empresa' => $id_empresa,
                'enable' => 1,
                'descripcion' => 'Categoria generada desde Shopify',
            ]);
        }

        return $categoria;
    }

    //actualizar inventario
    private function actualizarInventario($productoId, $cantidad, $bodegaId, $usuarioId)
    {
        $inventario = Inventario::where('id_producto', $productoId)
            ->where('id_bodega', $bodegaId)
            ->first();

        if ($inventario) {
            $inventario->update([
                'stock' => $cantidad
            ]);
            $producto = Producto::find($productoId);

            if ($inventario->stock > 0) {
                $producto->id_usuario = $usuarioId;
                $inventario->kardex($producto, 0, $producto->precio, $producto->costo);
            }
        } else {
            $inventario = Inventario::create([
                'id_producto' => $productoId,
                'id_bodega' => $bodegaId,
                'stock' => $cantidad,
                'stock_minimo' => 0,
                'stock_maximo' => 0,
            ]);
        }
        return [
            'id_producto' => $productoId,
            'id_bodega' => $bodegaId,
            'stock' => ['decrement' => $cantidad],
            'updated_at' => now()
        ];
    }

    public function storeImage($data)
    {
        // Log::info('storeImage', $data);

        try {
            // Buscar imagen existente por shopify_image_id
            if (isset($data['shopify_image_id']) && $data['shopify_image_id']) {
                $imagen = Imagen::where('shopify_image_id', $data['shopify_image_id'])->first();

                if ($imagen) {
                    // Si la imagen ya existe y tiene la misma URL, no hacer nada
                    if ($imagen->src === $data['src']) {
                        Log::info('Imagen ya existe con la misma URL, no se procesa', [
                            'imagen_id' => $imagen->id,
                            'shopify_image_id' => $data['shopify_image_id']
                        ]);
                        return $imagen;
                    }
                } else {
                    $imagen = new Imagen();
                }
            } else {
                $imagen = new Imagen();
            }

            $imagen->fill($data);

            // Solo procesar la imagen si es nueva o si la URL ha cambiado
            if (isset($data['src']) && $data['src']) {
                // Verificar si ya existe una imagen con la misma URL para el mismo producto
                $imagenExistente = Imagen::where('id_producto', $data['id_producto'])
                    ->where('src', $data['src'])
                    ->first();

                if ($imagenExistente && $imagenExistente->id !== $imagen->id) {
                    Log::info('Imagen ya existe para este producto con la misma URL', [
                        'imagen_existente_id' => $imagenExistente->id,
                        'producto_id' => $data['id_producto']
                    ]);
                    return $imagenExistente;
                }

                // Solo eliminar y recrear si la imagen ya existe y tiene una URL diferente
                if ($imagen->id && $imagen->img && $imagen->img != 'productos/default.jpg' && $imagen->src !== $data['src']) {
                    Storage::delete($imagen->img);
                    Log::info('Imagen anterior eliminada por cambio de URL', ['path' => $imagen->img]);
                }

                // Solo procesar si no existe o si la URL cambió
                if (!$imagen->id || $imagen->src !== $data['src']) {
                    try {
                        $imageContent = file_get_contents($data['src']);
                        if ($imageContent === false) {
                            throw new \Exception('No se pudo descargar la imagen desde: ' . $data['src']);
                        }

                        $resize = Image::make($imageContent)->resize(750, 750)->encode('jpg', 75);
                        $hash = md5($resize->__toString());
                        $path = "productos/{$hash}.jpg";

                        $fullPath = public_path('img/productos');
                        if (!file_exists($fullPath)) {
                            mkdir($fullPath, 0755, true);
                        }

                        $resize->save(public_path('img/' . $path), 50);
                        $imagen->img = "/" . $path;

                        Log::info('Imagen procesada y guardada', ['path' => $path]);
                    } catch (\Exception $e) {
                        Log::error('Error procesando imagen: ' . $e->getMessage());
                    }
                } else {
                    Log::info('Imagen ya procesada, no se vuelve a procesar', [
                        'imagen_id' => $imagen->id,
                        'src' => $data['src']
                    ]);
                }
            }

            $saved = $imagen->save();

            if ($saved) {
                Log::info('Imagen guardada exitosamente', ['imagen_id' => $imagen->id]);
            } else {
                Log::error('Error guardando imagen en base de datos');
            }

            return $imagen;
        } catch (\Exception $e) {
            Log::error('Error en storeImage: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Procesa el webhook de pedido cancelado de Shopify
     */
    public function procesarVentaCancelada($tokenEmpresa, Request $request)
    {
        // Log::info("Webhook de pedido cancelado recibido de Shopify", [
        //     'shopify_order_id' => $request->id,
        //     'token_empresa' => $tokenEmpresa
        // ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        try {
            // Buscar la venta por el ID del pedido de Shopify
            $shopifyOrderId = $request->id;
            $referencia = 'SHOPIFY-' . $shopifyOrderId;
            
            $venta = Venta::where('referencia_shopify', $referencia)
                ->where('id_empresa', $empresa->id)
                ->first();

            if (!$venta) {
                Log::warning("Venta no encontrada para el pedido cancelado de Shopify", [
                    'shopify_order_id' => $shopifyOrderId,
                    'referencia_buscada' => $referencia,
                    'empresa_id' => $empresa->id
                ]);
                return response()->json([
                    'status' => 'warning',
                    'mensaje' => 'Venta no encontrada para el pedido cancelado'
                ], 404);
            }

            // Verificar si la venta ya está anulada
            if ($venta->estado === 'Anulada') {
                Log::info("Venta ya está anulada", [
                    'venta_id' => $venta->id,
                    'shopify_order_id' => $shopifyOrderId
                ]);
                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Venta ya estaba anulada'
                ], 200);
            }

            DB::beginTransaction();

            // Marcar la venta como anulada
            $venta->update([
                'estado' => 'Anulada',
                'observaciones' => ($venta->observaciones ? $venta->observaciones . ' | ' : '') . 
                    'Pedido cancelado en Shopify el ' . now()->format('d/m/Y H:i:s')
            ]);

            // Verificar si se debe revertir el inventario según la configuración de Shopify
            $debeRevertirInventario = $this->debeRevertirInventario($request);
            
            // Log::info("Decisión de revertir inventario", [
            //     'debe_revertir' => $debeRevertirInventario,
            //     'shopify_order_id' => $shopifyOrderId
            // ]);

            // Solo restaurar el stock si Shopify indica que se debe revertir el inventario
            if ($debeRevertirInventario) {
                foreach ($venta->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto) {
                        $inventario = Inventario::where('id_producto', $producto->id)
                            ->where('id_bodega', $venta->id_bodega)
                            ->first();

                        if ($inventario) {
                            // Incrementar el stock
                            $inventario->increment('stock', $detalle->cantidad);
                            
                            // Validar y convertir valores numéricos
                            $cantidad = is_numeric($detalle->cantidad) ? (float)$detalle->cantidad : 0;
                            $precio = is_numeric($detalle->precio) ? (float)$detalle->precio : 0;
                            $costoProducto = is_numeric($producto->costo) ? (float)$producto->costo : 0;
                            
                            // Registrar en el kardex solo si tenemos valores válidos
                            if ($cantidad > 0) {
                                $inventario->kardex($venta, $cantidad, $precio, $costoProducto);
                            }
                            
                            // Log::info("Stock restaurado para producto", [
                            //     'producto_id' => $producto->id,
                            //     'cantidad_restaurada' => $cantidad,
                            //     'precio' => $precio,
                            //     'costo_usado' => $costoProducto,
                            //     'stock_actual' => $inventario->stock
                            // ]);
                        }
                    }
                }
            } else {
                Log::info("No se restaura el stock - opción 'Revertir inventario' no marcada en Shopify", [
                    'shopify_order_id' => $shopifyOrderId
                ]);
            }

            DB::commit();

            // Log::info("Venta anulada exitosamente desde Shopify", [
            //     'venta_id' => $venta->id,
            //     'shopify_order_id' => $shopifyOrderId,
            //     'estado_anterior' => $venta->getOriginal('estado')
            // ]);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta anulada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando cancelación de venta desde Shopify: ' . $e->getMessage(), [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la cancelación de la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determina si se debe revertir el inventario basado en el webhook de Shopify
     */
    private function debeRevertirInventario(Request $request)
    {
        
        // 1. Verificar si hay refunds con restock
        if (isset($request->refunds) && is_array($request->refunds)) {
            foreach ($request->refunds as $refund) {
                if (isset($refund['restock']) && $refund['restock'] === true) {
                    Log::info("Inventario debe revertirse - refund con restock encontrado", [
                        'refund_id' => $refund['id'] ?? 'N/A'
                    ]);
                    return true;
                }
            }
        }
        
        // 2. Verificar el cancel_reason y financial_status
        $cancelReason = $request->input('cancel_reason');
        $financialStatus = $request->input('financial_status');
        
        // Si el pedido está voided y no hay refunds, generalmente significa que se revierte el inventario
        if ($financialStatus === 'voided' && empty($request->refunds)) {
            Log::info("Inventario debe revertirse - pedido voided sin refunds", [
                'cancel_reason' => $cancelReason,
                'financial_status' => $financialStatus
            ]);
            return true;
        }
        
        // 3. Verificar si hay line_items con información de restock
        if (isset($request->line_items) && is_array($request->line_items)) {
            foreach ($request->line_items as $lineItem) {
                // Si el line item tiene fulfillable_quantity > 0, significa que no se ha enviado
                // y por tanto se debe revertir el inventario
                if (isset($lineItem['fulfillable_quantity']) && $lineItem['fulfillable_quantity'] > 0) {
                    Log::info("Inventario debe revertirse - line item con fulfillable_quantity > 0", [
                        'line_item_id' => $lineItem['id'] ?? 'N/A',
                        'fulfillable_quantity' => $lineItem['fulfillable_quantity']
                    ]);
                    return true;
                }
            }
        }
        
        // 4. Por defecto, si no hay información específica, asumir que NO se debe revertir
        // Esto es más seguro para evitar restaurar stock cuando no se debe
        Log::info("No se revierte inventario - no se encontró indicación clara de restock", [
            'cancel_reason' => $cancelReason,
            'financial_status' => $financialStatus,
            'has_refunds' => !empty($request->refunds)
        ]);
        
        return false;
    }

    /**
     * Busca o actualiza un cliente optimizando por shopify_customer_id, correo y teléfono
     * 
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    private function buscarOActualizarCliente($clienteData, $empresaId)
    {
        $shopifyCustomerId = $clienteData['shopify_customer_id'] ?? null;
        $correo = $clienteData['correo'] ?? null;
        $telefono = $clienteData['telefono'] ?? null;
        
        // Validaciones de seguridad para evitar asignaciones incorrectas
        if (!$this->validarDatosCliente($clienteData)) {
            Log::warning('Datos de cliente inválidos, creando cliente con datos mínimos', [
                'shopify_customer_id' => $shopifyCustomerId,
                'correo' => $correo,
                'telefono' => $telefono
            ]);
            
            // Crear cliente con datos mínimos válidos
            return $this->crearClienteMinimo($clienteData, $empresaId);
        }
        
        // 1. Si tenemos shopify_customer_id, buscar primero por ese campo
        if ($shopifyCustomerId) {
            $cliente = Cliente::where('shopify_customer_id', $shopifyCustomerId)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                Log::info('Cliente encontrado por shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'correo' => $cliente->correo,
                    'telefono' => $cliente->telefono
                ]);
                
                // Actualizar datos del cliente
                $cliente->update($clienteData);
                return $cliente;
            }
        }
        
        // 2. Si no se encontró por shopify_customer_id, buscar por correo
        if ($correo) {
            $cliente = Cliente::where('correo', $correo)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    Log::warning('Conflicto de shopify_customer_id detectado', [
                        'cliente_id' => $cliente->id,
                        'correo' => $correo,
                        'shopify_customer_id_existente' => $cliente->shopify_customer_id,
                        'shopify_customer_id_nuevo' => $shopifyCustomerId
                    ]);
                    
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                Log::info('Cliente encontrado por correo, actualizando shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'correo' => $correo,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'telefono_actual' => $cliente->telefono,
                    'telefono_nuevo' => $telefono
                ]);
                
                // Actualizar datos incluyendo el shopify_customer_id
                $cliente->update($clienteData);
                return $cliente;
            }
        }
        
        // 3. Si no se encontró por correo, buscar por teléfono (con validación adicional)
        if ($telefono) {
            $cliente = Cliente::where('telefono', $telefono)
                ->where('id_empresa', $empresaId)
                ->first();
                
            if ($cliente) {
                // Validar que no haya conflicto con shopify_customer_id existente
                if ($cliente->shopify_customer_id && $cliente->shopify_customer_id !== $shopifyCustomerId) {
                    Log::warning('Conflicto de shopify_customer_id detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'shopify_customer_id_existente' => $cliente->shopify_customer_id,
                        'shopify_customer_id_nuevo' => $shopifyCustomerId
                    ]);
                    
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                // Validar que el correo coincida si está disponible
                if ($correo && $cliente->correo && $cliente->correo !== $correo) {
                    Log::warning('Conflicto de correo detectado por teléfono', [
                        'cliente_id' => $cliente->id,
                        'telefono' => $telefono,
                        'correo_cliente' => $cliente->correo,
                        'correo_pedido' => $correo
                    ]);
                    
                    // Crear nuevo cliente para evitar conflicto
                    return $this->crearClienteMinimo($clienteData, $empresaId);
                }
                
                Log::info('Cliente encontrado por teléfono, actualizando shopify_customer_id', [
                    'cliente_id' => $cliente->id,
                    'telefono' => $telefono,
                    'shopify_customer_id' => $shopifyCustomerId,
                    'correo_actual' => $cliente->correo,
                    'correo_nuevo' => $correo
                ]);
                
                // Actualizar datos incluyendo el shopify_customer_id
                $cliente->update($clienteData);
                return $cliente;
            }
        }
        
        // 4. Si no existe, crear nuevo cliente
        Log::info('Creando nuevo cliente', [
            'correo' => $correo,
            'telefono' => $telefono,
            'shopify_customer_id' => $shopifyCustomerId
        ]);
        
        return Cliente::create($clienteData);
    }

    /**
     * Valida los datos del cliente para evitar asignaciones incorrectas
     * 
     * @param array $clienteData
     * @return bool
     */
    private function validarDatosCliente($clienteData)
    {
        $correo = $clienteData['correo'] ?? null;
        $nombre = $clienteData['nombre'] ?? null;
        $apellido = $clienteData['apellido'] ?? null;
        
        // Validar que tenga al menos un nombre
        if (empty($nombre) && empty($apellido)) {
            Log::warning('Cliente sin nombre válido', [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'correo' => $correo
            ]);
            return false;
        }
        
        // Validar email si existe
        if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Email inválido', [
                'correo' => $correo,
                'nombre' => $nombre
            ]);
            return false;
        }
        
        // Validar que no sea un email genérico o de prueba
        if ($correo && $this->esEmailGenerico($correo)) {
            Log::warning('Email genérico detectado', [
                'correo' => $correo,
                'nombre' => $nombre
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Verifica si un email es genérico o de prueba
     * 
     * @param string $email
     * @return bool
     */
    private function esEmailGenerico($email)
    {
        $emailsGenericos = [
            'test@example.com',
            'test@test.com',
            'admin@shopify.com',
            'noreply@shopify.com',
            'support@shopify.com',
            'info@shopify.com',
            'contact@shopify.com'
        ];
        
        $emailLower = strtolower($email);
        
        // Verificar emails genéricos exactos
        if (in_array($emailLower, $emailsGenericos)) {
            return true;
        }
        
        // Verificar patrones genéricos
        $patronesGenericos = [
            '/^test\d*@/',
            '/^admin\d*@/',
            '/^user\d*@/',
            '/^customer\d*@/',
            '/^shopify\d*@/',
            '/^demo\d*@/'
        ];
        
        foreach ($patronesGenericos as $patron) {
            if (preg_match($patron, $emailLower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Crea un cliente con datos mínimos válidos
     * 
     * @param array $clienteData
     * @param int $empresaId
     * @return Cliente
     */
    private function crearClienteMinimo($clienteData, $empresaId)
    {
        $clienteMinimo = [
            'nombre' => $clienteData['nombre'] ?? 'Cliente',
            'apellido' => $clienteData['apellido'] ?? 'Shopify',
            'correo' => $clienteData['correo'] ?? 'cliente@shopify.com',
            'telefono' => $clienteData['telefono'] ?? '',
            'direccion' => $clienteData['direccion'] ?? '',
            'pais' => $clienteData['pais'] ?? '',
            'municipio' => $clienteData['municipio'] ?? '',
            'departamento' => $clienteData['departamento'] ?? '',
            'tipo' => 'Persona',
            'enable' => 1,
            'id_empresa' => $empresaId,
            'id_usuario' => $clienteData['id_usuario'] ?? null,
            'shopify_customer_id' => $clienteData['shopify_customer_id'] ?? null,
        ];
        
        Log::info('Creando cliente mínimo', [
            'cliente_minimo' => $clienteMinimo
        ]);
        
        return Cliente::create($clienteMinimo);
    }

    /**
     * Mapea el estado financiero de Shopify al estado de SmartPyme
     * 
     * @param string $shopifyStatus
     * @return string
     */
    private function mapearEstado($shopifyStatus)
    {
        $mapeo = [
            'pending' => 'Pendiente',
            'authorized' => 'Pendiente',
            'partially_paid' => 'Pendiente',
            'paid' => 'Pagada',
            'partially_refunded' => 'Pagada',
            'refunded' => 'Reembolsada',
            'voided' => 'Anulada'
        ];

        return $mapeo[$shopifyStatus] ?? 'Pendiente';
    }

    /**
     * Actualiza las cantidades de productos en una venta existente
     * 
     * @param Venta $venta
     * @param Request $request
     * @return void
     */
    private function actualizarCantidadesProductos($venta, $request)
    {
        Log::info("Iniciando actualización de cantidades de productos", [
            'venta_id' => $venta->id,
            'shopify_order_id' => $request->id,
            'line_items_count' => count($request->line_items ?? [])
        ]);

        $lineItems = $request->line_items ?? [];
        
        foreach ($lineItems as $item) {
            // Buscar el producto por variant_id o SKU
            $producto = null;
            
            if (!empty($item['variant_id'])) {
                $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->first();
            }
            
            if (!$producto && !empty($item['sku'])) {
                $producto = Producto::where('codigo', $item['sku'])
                    ->where('id_empresa', $venta->id_empresa)
                    ->first();
            }
            
            if (!$producto) {
                Log::warning("Producto no encontrado para actualizar cantidad", [
                    'variant_id' => $item['variant_id'] ?? 'N/A',
                    'sku' => $item['sku'] ?? 'N/A',
                    'title' => $item['title'] ?? 'N/A'
                ]);
                continue;
            }
            
            // Buscar el detalle de venta existente
            $detalle = $venta->detalles()
                ->where('id_producto', $producto->id)
                ->first();
                
            if (!$detalle) {
                Log::warning("Detalle de venta no encontrado para producto", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'producto_nombre' => $producto->nombre
                ]);
                continue;
            }
            
            $cantidadAnterior = $detalle->cantidad;
            // Usar current_quantity si está disponible, sino quantity
            $cantidadNueva = $item['current_quantity'] ?? $item['quantity'];
            
            Log::info("Comparando cantidades de producto", [
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $cantidadNueva,
                'quantity_shopify' => $item['quantity'],
                'current_quantity_shopify' => $item['current_quantity'] ?? 'N/A',
                'fulfillable_quantity_shopify' => $item['fulfillable_quantity'] ?? 'N/A',
                'diferencia' => $cantidadNueva - $cantidadAnterior
            ]);
            
            // Solo actualizar si la cantidad ha cambiado
            if ($cantidadAnterior != $cantidadNueva) {
                Log::info("Actualizando cantidad de producto", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad_anterior' => $cantidadAnterior,
                    'cantidad_nueva' => $cantidadNueva,
                    'diferencia' => $cantidadNueva - $cantidadAnterior
                ]);
                
                // Actualizar la cantidad en el detalle
                $detalle->update([
                    'cantidad' => $cantidadNueva,
                    'total' => $cantidadNueva * $detalle->precio
                ]);
                
                // Ajustar el inventario
                $diferenciaStock = $cantidadNueva - $cantidadAnterior;
                
                if ($diferenciaStock != 0) {
                    $inventario = Inventario::where('id_producto', $producto->id)
                        ->where('id_bodega', $venta->id_bodega)
                        ->first();
                        
                    if ($inventario) {
                        if ($diferenciaStock > 0) {
                            // Se agregaron productos, reducir stock
                            $inventario->decrement('stock', $diferenciaStock);
                        } else {
                            // Se quitaron productos, incrementar stock
                            $inventario->increment('stock', abs($diferenciaStock));
                        }
                        
                        // Registrar en el kardex
                        $inventario->kardex($venta, abs($diferenciaStock), $detalle->precio, $producto->costo);
                        
                        Log::info("Inventario ajustado por cambio de cantidad", [
                            'producto_id' => $producto->id,
                            'diferencia_stock' => $diferenciaStock,
                            'stock_actual' => $inventario->stock
                        ]);
                    }
                }
            } else {
                Log::info("Cantidad sin cambios para producto", [
                    'venta_id' => $venta->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidadAnterior
                ]);
            }
        }
        
        // Recalcular totales de la venta
        $this->recalcularTotalesVenta($venta);
    }

    /**
     * Recalcula los totales de una venta después de actualizar cantidades
     * 
     * @param Venta $venta
     * @return void
     */
    private function recalcularTotalesVenta($venta)
    {
        $subtotal = 0;
        $iva = 0;
        $gravada = 0;
        
        foreach ($venta->detalles as $detalle) {
            $subtotal += $detalle->subtotal;
            $iva += $detalle->iva;
            $gravada += $detalle->gravada;
        }
        
        // Para ventas de Shopify, el total debe ser gravada + iva
        // Redondear a 2 decimales para evitar problemas de precisión
        $total = round($gravada + $iva, 2);
        
        $venta->update([
            'sub_total' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'total' => $total
        ]);
        
        Log::info("Totales de venta recalculados", [
            'venta_id' => $venta->id,
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'total' => $total
        ]);
    }

    /**
     * Procesa el webhook de pedido actualizado de Shopify
     * 
     * @param string $tokenEmpresa
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function procesarVentaActualizada($tokenEmpresa, Request $request)
    {
        Log::info("Webhook de pedido actualizado recibido de Shopify", [
            'shopify_order_id' => $request->id,
            'token_empresa' => $tokenEmpresa,
            'financial_status' => $request->financial_status ?? 'N/A',
            'fulfillment_status' => $request->fulfillment_status ?? 'N/A'
        ]);

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            Log::error("Token de empresa Shopify no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }

        try {
            // Buscar la venta existente
            $shopifyOrderId = $request->id;
            $referencia = 'SHOPIFY-' . $shopifyOrderId;
            
            $venta = Venta::where('referencia_shopify', $referencia)
                ->where('id_empresa', $empresa->id)
                ->first();

            if (!$venta) {
                Log::warning("Venta no encontrada para actualización", [
                    'shopify_order_id' => $shopifyOrderId,
                    'referencia_buscada' => $referencia,
                    'empresa_id' => $empresa->id
                ]);
                return response()->json([
                    'status' => 'warning',
                    'mensaje' => 'Venta no encontrada para actualizar'
                ], 404);
            }

            // Actualizar estado de la venta si es necesario
            $nuevoEstado = $this->mapearEstado($request->financial_status ?? 'pending');
            
            if ($venta->estado !== $nuevoEstado) {
                $venta->update([
                    'estado' => $nuevoEstado,
                    'observaciones' => ($venta->observaciones ? $venta->observaciones . ' | ' : '') . 
                        'Pedido actualizado en Shopify el ' . now()->format('d/m/Y H:i:s')
                ]);
                
                Log::info("Estado de venta actualizado", [
                    'venta_id' => $venta->id,
                    'estado_anterior' => $venta->getOriginal('estado'),
                    'estado_nuevo' => $nuevoEstado,
                    'shopify_order_id' => $shopifyOrderId
                ]);
            }

            // Actualizar cantidades de productos si han cambiado
            $this->actualizarCantidadesProductos($venta, $request);

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta actualizada correctamente',
                'venta_id' => $venta->id,
                'estado' => $venta->estado
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error procesando actualización de venta desde Shopify: ' . $e->getMessage(), [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la actualización de la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesa el webhook de prueba enviado por Shopify
     * 
     * @param Request $request
     * @param Empresa $empresa
     * @return \Illuminate\Http\JsonResponse
     */
    private function procesarPruebaWebhook(Request $request, $empresa)
    {
        // Log::info("Webhook de prueba recibido de Shopify", [
        //     'empresa_id' => $empresa->id,
        //     'empresa_nombre' => $empresa->nombre,
        //     'timestamp' => now(),
        //     'headers' => $request->headers->all(),
        //     'payload' => $request->all()
        // ]);

        // Verificar que el webhook de prueba contenga los datos esperados
        $testData = $request->all();
        
        // Shopify envía un payload de prueba con información básica
        $response = [
            'status' => 'success',
            'message' => 'Webhook de prueba procesado correctamente',
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'shopify_status' => $empresa->shopify_status
            ],
            'webhook_info' => [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop_domain' => $request->header('X-Shopify-Shop-Domain'),
                'webhook_id' => $request->header('X-Shopify-Webhook-Id'),
                'timestamp' => now()->toISOString()
            ],
            'test_data_received' => !empty($testData)
        ];

        // Log::info("Respuesta del webhook de prueba", $response);

        return response()->json($response, 200);
    }
}
