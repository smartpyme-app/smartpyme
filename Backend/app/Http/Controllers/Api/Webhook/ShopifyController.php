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
use App\Services\ConsumoPuntosService;

class ShopifyController extends Controller
{
    protected $transformer;
    protected $cache;


    public function __construct(ShopifyTransformer $transformer, ShopifySyncCache $cache)
    {
        $this->transformer = $transformer;
        $this->cache = $cache;
    }

    public function procesarWebhook($tokenEmpresa, Request $request)
    {
        Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");
        Log::info("Datos del webhook: ", $request->all());

        $webhookTopic = $request->header('X-Shopify-Topic');

        Log::info("Tipo de webhook: {$webhookTopic}");


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
                case 'orders/create':
                    return $this->procesarVenta($tokenEmpresa, $request);

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
        Log::info("Producto desde Shopify", ['product_id' => $request->id]);

        $productosData = $this->transformer->transformarProductoDesdeShopify(
            $request->all(),
            $empresa->id,
            $usuario->id,
            $usuario->id_sucursal
        );

        $categoriaData = $this->transformer->transformarCategoriaDesdeShopify(
            $request->all(),
            $empresa->id
        );
        Log::info("Categoria desde Shopify", ['categoria_id' => $categoriaData]);

        foreach ($productosData as $productoData) {
            $producto = $this->buscarProductoExistente($request->id, $productoData, $empresa->id);
            Log::info("Producto existente", ['producto_id' => $producto->id]);

            Log::info("Data producto", ['producto_id' => $productoData]);
            $categoria = $this->obtenerCategoria($request->all(), $categoriaData, $empresa->id);
            $productoData['id_categoria'] = $categoria->id;

            if ($producto) {
                if ($this->cache->isShopifyDataDifferent($producto, $productoData)) {
                    $this->cache->lockSync($producto->id);

                    $this->actualizarProductoExistente($producto, $productoData, $usuario);

                    $producto->fresh();
                    $this->cache->saveProductSnapshot($producto);

                    Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
                } else {
                    Log::info("Producto sin cambios desde Shopify", ['producto_id' => $producto->id]);
                }
            } else {
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
        return Producto::where('shopify_product_id', $shopifyId)
            ->where('shopify_variant_id', $productoData['shopify_variant_id'])
            ->where('id_empresa', $empresaId)
            ->first();
    }

    private function obtenerCategoria($requestData, $categoriaData, $empresaId)
    {
        $nombreCategoria = empty($requestData['category']) ? 'General' : $categoriaData['nombre'];
        return $this->buscarCategoria($nombreCategoria, $empresaId);
    }

    private function actualizarProductoExistente($producto, $productoData, $usuario)
    {
        $stockActual = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->value('stock') ?? 0;

        $stockNuevo = $productoData['stock'] ?? 0;

        $producto->update($productoData);

        if ($stockActual != $stockNuevo) {
            $this->actualizarInventario($producto->id, $stockNuevo, $usuario->id_bodega, $usuario->id);

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
        $producto = Producto::create($productoData);
        
        $this->actualizarInventario($producto->id, $productoData['stock'], $usuario->id_bodega, $usuario->id);
        $this->procesarImagenes($request, $producto->id);

        $inventario = \App\Models\Inventario\Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $usuario->id_bodega)
            ->first();
            
        if ($inventario) {
            $this->cache->saveInventorySnapshot($inventario, $producto->id);
        }

        Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        
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
            Log::info($imagenData);
            Log::info("Procesando imagen", ['imagen_id' => $imagen['id']]);
            $this->storeImage($imagenData);
        }
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
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

        try {
            DB::beginTransaction();

            $request->merge([
                'id_empresa' => $usuario->id_empresa,
                'id_usuario' => $usuario->id,
                'id_bodega' => $usuario->id_bodega,
                'id_sucursal' => $usuario->id_sucursal,
                'id_documento' => $documento->id,
                'id_canal' => $empresa->shopify_canal_id
            ]);

            $clienteData = $this->transformer->transformarCliente($request->all());
            Log::info($clienteData);
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );

            $ventaData = $this->transformer->transformarVenta(
                $request->all(),
                $cliente->id,
                $documento->id,
                $documento->correlativo
            );
            Log::info($ventaData);
            $venta = Venta::create($ventaData);

            Log::info($request->line_items);
            foreach ($request->line_items as $item) {
                Log::info($item['variant_id']);
                $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                    ->where('id_empresa', $usuario->id_empresa)
                    ->first();

                if (!$producto && !empty($item['sku'])) {
                    $producto = Producto::where('codigo', $item['sku'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                if (!$producto) {
                    $productoData = $this->transformer->transformarProducto(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                }

                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

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

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->estado == 'Pagada' && $venta->id_cliente) {
                try {
                    $consumoPuntosService = app(ConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización en Shopify', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // No se interrumpe la transacción por errores en puntos
                }
            }

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
        Log::info('storeImage', $data);

        try {
            if (isset($data['shopify_image_id']) && $data['shopify_image_id']) {
                $imagen = Imagen::where('shopify_image_id', $data['shopify_image_id'])->first();

                if (!$imagen) {
                    $imagen = new Imagen();
                    Log::info('Creando nueva imagen');
                } else {
                    Log::info('Imagen existente encontrada', ['imagen_id' => $imagen->id]);
                }
            } else {
                $imagen = new Imagen();
                Log::info('Creando nueva imagen sin shopify_image_id');
            }

            $imagen->fill($data);
            Log::info('Imagen después de fill', $imagen->toArray());

            if (isset($data['src']) && $data['src']) {
                Log::info('Procesando src', ['src' => $data['src']]);

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

                    Log::info('Imagen procesada y guardada', ['path' => $path]);
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
}
