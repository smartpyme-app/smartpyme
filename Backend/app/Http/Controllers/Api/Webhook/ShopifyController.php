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

class ShopifyController extends Controller
{
    protected $transformer;

    public function __construct(ShopifyTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function procesarWebhook($tokenEmpresa, Request $request)
    {
        Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");

      
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
                return $this->procesarVenta($request, $empresa, $usuario);

            case 'products/create':
                return $this->procesarProductoCreado($request, $empresa, $usuario);

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

    private function procesarProductoCreado(Request $request, $empresa, $usuario)
    {
        Log::info("Producto creado en Shopify", ['product_id' => $request->id]);

        $productoData = $this->transformer->transformarProductoDesdeShopify($request->all(), $empresa->id, $usuario->id, $usuario->id_sucursal);
        $productoExistente = Producto::where('shopify_product_id', $request->id)
            ->where('id_empresa', $empresa->id)
            ->where('codigo', $productoData['codigo'])
            ->first();

        // ⭐ VERIFICAR CACHE LOCK CON ID INTERNO SI EL PRODUCTO EXISTE
        if ($productoExistente && Cache::has("shopify_sync_lock_{$productoExistente->id}")) {
            Log::info("Producto en período de gracia - ignorando webhook de creación", [
                'product_id' => $request->id,
                'producto_id' => $productoExistente->id,
                'webhook_type' => 'products/create'
            ]);
            return response()->json(['status' => 'ignored', 'mensaje' => 'En período de gracia'], 200);
        }

        $productoData = $this->transformer->transformarProductoDesdeShopify($request->all(), $empresa->id, $usuario->id, $usuario->id_sucursal);
        $productoExistente = Producto::where('shopify_product_id', $request->id)
            ->where('id_empresa', $empresa->id)
            ->where('codigo', $productoData['codigo'])
            ->first();
        $categoria = $this->buscarCategoria('General', $empresa->id);

        if (!$productoExistente) {
            Log::info("Producto no existe en tu sistema", ['product_id' => $request->id]);
            $productoData['id_categoria'] = $categoria->id;
            Log::info($productoData);
            $producto = Producto::create($productoData);
            $this->actualizarInventario($producto->id, $productoData['stock'], $usuario->id_bodega, $usuario->id);
            Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
            $this->procesarImagenes($request, $producto->id);
        } else {
            Log::info("Producto existente en tu sistema", ['producto_id' => $productoExistente->id]);
            if (!$productoExistente->categoria) {
                $productoData['id_categoria'] = $categoria->id;
            }
            Log::info($productoData);
            $this->actualizarInventario($productoExistente->id, $productoData['stock'], $usuario->id_bodega, $usuario->id);
            Log::info("Producto actualizado en tu sistema", ['producto_id' => $productoExistente->id]);
            $productoExistente->update($productoData);
            $this->procesarImagenes($request, $productoExistente->id);
        }

        return response()->json(['status' => 'success', 'mensaje' => 'Producto procesado'], 200);
    }

    private function procesarProductoActualizado(Request $request, $empresa, $usuario)
    {
        Log::info("Producto actualizado en Shopify", ['product_id' => $request->id]);

        $productoData = $this->transformer->transformarProductoDesdeShopify($request->all(), $empresa->id, $usuario->id, $usuario->id_sucursal);

        $producto = Producto::where('shopify_product_id', $request->id)
            ->where('id_empresa', $empresa->id)
            ->where('codigo', $productoData['codigo'])
            ->first();

        if ($producto && Cache::has("shopify_sync_lock_{$producto->id}")) {
            Log::info("Producto en período de gracia - ignorando webhook de actualización", [
                'product_id' => $request->id,
                'producto_id' => $producto->id,
                'webhook_type' => 'products/update'
            ]);
            return response()->json(['status' => 'ignored', 'mensaje' => 'En período de gracia'], 200);
        }

        $productoData = $this->transformer->transformarProductoDesdeShopify($request->all(), $empresa->id, $usuario->id, $usuario->id_sucursal);

        $producto = Producto::where('shopify_product_id', $request->id)
            ->where('id_empresa', $empresa->id)
            ->where('codigo', $productoData['codigo'])
            ->first();

        $categoria = $this->buscarCategoria('General', $empresa->id);

        if ($producto) {
            if (empty($producto->id_categoria)) {
                Log::info("Asignando categoría al producto", ['producto_id' => $producto->id]);
                $productoData['id_categoria'] = $categoria->id;
            }
            Log::info($productoData);
            $producto->update($productoData);
            $this->actualizarInventario($producto->id, $productoData['stock'], $usuario->id_bodega, $usuario->id);
            $this->procesarImagenes($request, $producto->id);
            Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
        } else {
            $productoData['id_categoria'] = $categoria->id;
            $producto = Producto::create($productoData);
            $this->actualizarInventario($producto->id, $productoData['stock'], $usuario->id_bodega, $usuario->id);
            $this->procesarImagenes($request, $producto->id);
            Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        }

        return response()->json(['status' => 'success', 'mensaje' => 'Producto actualizado'], 200);
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