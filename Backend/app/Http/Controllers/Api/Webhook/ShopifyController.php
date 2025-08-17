<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToShopify;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
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

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        Log::info("Webhook Shopify recibido para token: {$tokenEmpresa}");

        // Verificar webhook de prueba
        // if ($request->has('test') && $request->test === true) {
        //     return response()->json(['message' => 'Webhook de prueba válido'], 200);
        // }

        Log::info("Token de empresa Shopify: {$tokenEmpresa}");

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
}