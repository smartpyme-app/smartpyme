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


    public function __construct(ShopifyTransformer $transformer, ShopifySyncCache $cache)
    {
        $this->transformer = $transformer;
        $this->cache = $cache;
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
            $usuario->id_sucursal
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

        // Marcar que este producto está siendo actualizado desde Shopify
        $productoData['syncing_from_shopify'] = true;
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

        $this->procesarImagenes(request(), $producto->id);
    }


    private function crearNuevoProducto($productoData, $usuario, $request)
    {
        // Extraer datos especiales que no van al modelo
        $stock = $productoData['_stock'] ?? 0;
        $idUsuario = $productoData['_id_usuario'] ?? $usuario->id;
        $idSucursal = $productoData['_id_sucursal'] ?? $usuario->id_sucursal;
        
        // Limpiar datos especiales del array
        unset($productoData['_stock'], $productoData['_id_usuario'], $productoData['_id_sucursal']);
        
        // Marcar que este producto viene de Shopify para evitar ciclos
        $productoData['syncing_from_shopify'] = true;
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
        // Log::info("=== PROCESANDO CLIENTE CREADO DESDE SHOPIFY ===", [
        //     'shopify_customer_id' => $request->id,
        //     'customer_email' => $request->email ?? 'N/A'
        // ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            // Log::info("Datos del cliente transformados", $clienteData);
            
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );
            
            // Log::info("Cliente creado/actualizado", [
            // 'cliente_id' => $cliente->id, 
            //     'correo' => $cliente->correo,
            //     'shopify_customer_id' => $request->id
            // ]);

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
        // Log::info("=== PROCESANDO CLIENTE ACTUALIZADO DESDE SHOPIFY ===", [
        //     'shopify_customer_id' => $request->id,
        //     'customer_email' => $request->email ?? 'N/A'
        // ]);

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
            ]);

            $clienteData = $this->transformer->transformarClienteDesdeShopify($request->all());
            // Log::info("Datos del cliente transformados", $clienteData);
            
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );
            
            // Log::info("Cliente actualizado", [
            //     'cliente_id' => $cliente->id, 
            //     'correo' => $cliente->correo,
            //     'shopify_customer_id' => $request->id
            // ]);

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
            // Log::info("Datos del cliente transformados", $clienteData);
            
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );
            
            // Log::info("Cliente creado/encontrado", ['cliente_id' => $cliente->id, 'correo' => $cliente->correo]);

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
            if (isset($data['shopify_image_id']) && $data['shopify_image_id']) {
                $imagen = Imagen::where('shopify_image_id', $data['shopify_image_id'])->first();

                if (!$imagen) {
                    $imagen = new Imagen();
                    // Log::info('Creando nueva imagen');
                } else {
                    // Log::info('Imagen existente encontrada', ['imagen_id' => $imagen->id]);
                }
            } else {
                $imagen = new Imagen();
                // Log::info('Creando nueva imagen sin shopify_image_id');
            }

            $imagen->fill($data);
            // Log::info('Imagen después de fill', $imagen->toArray());

            if (isset($data['src']) && $data['src']) {
                // Log::info('Procesando src', ['src' => $data['src']]);

                if ($imagen->id && $imagen->img && $imagen->img != 'productos/default.jpg') {
                    Storage::delete($imagen->img);
                    Log::info('Imagen anterior eliminada', ['path' => $imagen->img]);
                }

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

                    // Log::info('Imagen procesada y guardada', ['path' => $path]);
                } catch (\Exception $e) {
                    Log::error('Error procesando imagen: ' . $e->getMessage());
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
