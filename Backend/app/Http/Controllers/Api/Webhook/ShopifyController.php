<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToShopify;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Services\ShopifyApiClient;
use Illuminate\Http\Request;
use App\Services\ShopifyTransformer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Detectar tipo de webhook por header
        $webhookTopic = $request->header('X-Shopify-Topic');

        Log::info("Tipo de webhook: {$webhookTopic}");
        Log::info($request->all());

        // Validar empresa y usuario (mismo código)
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

        // Procesar según el tipo de webhook
        //  try {
        switch ($webhookTopic) {
            case 'orders/create':
                return $this->procesarOrden($request, $empresa, $usuario);

            case 'products/create':
                return $this->procesarProductoCreado($request, $empresa, $usuario);

            case 'products/update':
                return $this->procesarProductoActualizado($request, $empresa, $usuario);

            default:
                Log::warning("Tipo de webhook no manejado: {$webhookTopic}");
                return response()->json(['message' => 'Webhook recibido pero no procesado'], 200);
        }
        // } catch (\Exception $e) {
        //     Log::error("Error procesando webhook Shopify: " . $e->getMessage());
        //     return response()->json([
        //         'status' => 'error',
        //         'mensaje' => 'Error al procesar webhook',
        //         'error' => $e->getMessage()
        //     ], 500);
        // }
    }



    private function procesarProductoCreado(Request $request, $empresa, $usuario)
    {
        Log::info("Producto creado en Shopify", ['product_id' => $request->id]);

        $productoData = $this->transformer->transformarProductoDesdeShopify($request->all(), $empresa->id, $usuario->id, $usuario->id_sucursal);
        $productoExistente = Producto::where('shopify_product_id', $request->id)
            ->where('id_empresa', $empresa->id)
            ->where('codigo', $productoData['codigo'])
            ->first();
        $categoria = $this->buscarCategoria('General', $empresa->id);

        if (!$productoExistente) {
            Log::info("Producto no existe en tu sistema", ['product_id' => $request->id]);
            $productoData['id_categoria'] = $categoria->id;  // ← Siempre asignar para productos nuevos
            Log::info($productoData);
            $producto = Producto::create($productoData);
            Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        } else {
            Log::info("Producto existente en tu sistema", ['producto_id' => $productoExistente->id]);
            // Solo verificar categoría si el producto existe
            if (!$productoExistente->categoria) {
                $productoData['id_categoria'] = $categoria->id;
            }
            Log::info($productoData);
            Log::info("Producto actualizado en tu sistema", ['producto_id' => $productoExistente->id]);
            $productoExistente->update($productoData);
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

        $categoria = $this->buscarCategoria('General', $empresa->id);

        if ($producto) {
            // SIEMPRE asignar categoría si no existe o es null
            if (empty($producto->id_categoria)) {
                Log::info("Asignando categoría al producto", ['producto_id' => $producto->id]);
                $productoData['id_categoria'] = $categoria->id;
            }
            Log::info($productoData);
            $producto->update($productoData);
            Log::info("Producto actualizado desde Shopify", ['producto_id' => $producto->id]);
        } else {
            $productoData['id_categoria'] = $categoria->id;
            $producto = Producto::create($productoData);
            Log::info("Producto creado desde Shopify", ['producto_id' => $producto->id]);
        }

        return response()->json(['status' => 'success', 'mensaje' => 'Producto actualizado'], 200);
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

        // Verificar el webhook signature
        // if (!$this->verificarWebhookSignature($request, $empresa->shopify_consumer_secret)) {
        //     Log::error("Firma de webhook Shopify inválida");
        //     return response()->json([
        //         'status' => 'error',
        //         'mensaje' => 'Firma de webhook inválida'
        //     ], 401);
        // }

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

            // 1. Crear/actualizar Cliente
            $clienteData = $this->transformer->transformarCliente($request->all());
            Log::info($clienteData);
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );

            // 2. Crear Venta
            $ventaData = $this->transformer->transformarVenta(
                $request->all(),
                $cliente->id,
                $documento->id,
                $documento->correlativo
            );
            Log::info($ventaData);
            $venta = Venta::create($ventaData);



            // 3. Procesar líneas de productos
            Log::info($request->line_items);
            foreach ($request->line_items as $item) {
                Log::info($item['variant_id']);
                // Buscar producto primero por shopify_id, luego por SKU
                $producto = Producto::where('shopify_variant_id', $item['variant_id'])
                    ->where('id_empresa', $usuario->id_empresa)
                    ->first();

                if (!$producto && !empty($item['sku'])) {
                    $producto = Producto::where('codigo', $item['sku'])
                        ->where('id_empresa', $usuario->id_empresa)
                        ->first();
                }

                if (!$producto) {
                    // Crear el producto si no existe
                    $productoData = $this->transformer->transformarProducto(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                }

                // Crear detalle de venta
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

            // Incrementar correlativo del documento
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

    private function verificarWebhookSignature(Request $request, $webhookSecret)
    {
        Log::info("Verificando firma de webhook Shopify");
        //ver los headers
        Log::info($request->headers->all());
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $body = $request->getContent();

        if (!$hmacHeader || !$webhookSecret) {
            return false;
        }

        $calculatedHmac = base64_encode(hash_hmac('sha256', $body, $webhookSecret, true));

        return hash_equals($hmacHeader, $calculatedHmac);
    }
    //crear o Buscar categoria
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
}
