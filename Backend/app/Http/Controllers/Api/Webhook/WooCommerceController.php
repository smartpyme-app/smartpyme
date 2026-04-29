<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsToWooCommerce;
use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Services\WooCommerceApiClient;
use Illuminate\Http\Request;
use App\Services\WooCommerceTransformer;
use App\Services\WooCommerceInboundProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\FidelizacionCliente\ConsumoPuntosService;

class WooCommerceController extends Controller
{
    protected $transformer;

    public function __construct(WooCommerceTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        Log::info("Webhook recibido para token: {$tokenEmpresa}");

        if ($request->webhook_id != null) {
            return response()->json(['message' => 'Webhook válido'], 200);
        }

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)->where('woocommerce_status', 'connected')->first();

        if (!$empresa) {
            Log::error("Token de empresa no válido: {$tokenEmpresa}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado'
            ], 401);
        }
        $usuario = User::where('id_empresa', $empresa->id)->where('woocommerce_status', 'connected')->first();

        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado'
            ], 401);
        }


        if ($empresa->facturacion_electronica) {
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)->where('nombre', 'Factura')->where('activo', true)->first();
        } else {
            //Buscar Ticket
            $documento = Documento::where('id_sucursal', $usuario->id_sucursal)->where('nombre', 'Ticket')->where('activo', true)->first();
        }

        $wooOrderId = $request->input('id');
        $referenciaWooCommerce = $wooOrderId ? 'WOOC-' . $wooOrderId : null;

        if ($referenciaWooCommerce) {
            $ventaExistente = Venta::withoutGlobalScope('empresa')
                ->where('referencia_woocommerce', $referenciaWooCommerce)
                ->where('id_empresa', $empresa->id)
                ->first();

            if ($ventaExistente) {
                Log::info('Venta duplicada WooCommerce - orden ya procesada', [
                    'woo_order_id' => $wooOrderId,
                    'venta_id_existente' => $ventaExistente->id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'mensaje' => 'Orden ya procesada previamente',
                    'venta_id' => $ventaExistente->id,
                    'duplicado' => true
                ], 200);
            }
        }

        try {
            DB::beginTransaction();

            $request->merge(['id_empresa' => $usuario->id_empresa, 'id_usuario' => $usuario->id, 'id_bodega' => $usuario->id_bodega, 'id_sucursal' => $usuario->id_sucursal, 'id_documento' => $documento->id, 'id_canal' => $empresa->woocommerce_canal_id]);

            $wooData = $request->all();
            if (!isset($wooData['billing']) && !isset($wooData['billing_address'])) {
                Log::warning('Webhook WooCommerce: payload sin billing ni billing_address', [
                    'claves' => array_keys($wooData),
                    'order_id' => $wooData['id'] ?? null
                ]);
            }

            $clienteData = $this->transformer->transformarCliente($wooData);
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );

            $ventaData = $this->transformer->transformarVenta($wooData, $cliente->id, $documento->id, $documento->correlativo);
            $ventaData['referencia_woocommerce'] = $referenciaWooCommerce;

            $venta = Venta::create($ventaData);

            $lineItems = $request->line_items ?? $request->input('line_items', []);
            if (empty($lineItems)) {
                throw new \Exception('El pedido no contiene productos (line_items vacío)');
            }
            foreach ($lineItems as $item) {
                //$producto = Producto::where('codigo', $item['sku'])->where('id_empresa', $usuario->id_empresa)->first();
                //primero buscar por woocommerce_id si no por sku

                // variation_id cuando es variación, sino product_id (item['id'] puede ser el order_item_id)
                $wooProductId = $item['variation_id'] ?? $item['product_id'] ?? $item['id'] ?? null;
                $producto = $wooProductId
                    ? Producto::where('woocommerce_id', $wooProductId)->where('id_empresa', $usuario->id_empresa)->first()
                    : null;

                if (!$producto) {
                    $producto = Producto::where('codigo', $item['sku'] ?? '')->where('id_empresa', $usuario->id_empresa)->first();
                }

                if (!$producto) {
                    return response()->json([
                        'status' => 'error',
                        'mensaje' => 'Producto no encontrado: ' . ($item['sku'] ?? $item['name'] ?? 'SKU desconocido')
                    ], 500);
                    // throw new \Exception("Producto no encontrado: {$item['sku']}");
                    //crear el producto
                    $productoData = $this->transformer->transformarProducto($item, $usuario->id_empresa, $usuario->id, $usuario->id_sucursal);
                    $producto = Producto::create($productoData);
                }

                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                $inventarioData = $this->transformer->actualizarInventario(
                    $producto->id,
                    $item['quantity'],
                    $venta->id_bodega
                );

                Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->decrement('stock', $item['quantity']);

                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();

                if ($inventario) {
                    $inventario->kardex($venta, $item['quantity'], $item['price']);
                }

                // Inventario::updateOrCreate(
                //     ['id_producto' => $producto->id, 'id_bodega' => $venta->id_bodega],
                //     ['stock' => $inventarioData['stock']]
                // );
            }

            $documento = Documento::findOrfail($venta->id_documento);
            $documento->increment('correlativo');

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->estado == 'Pagada' && $venta->id_cliente) {
                try {
                    $consumoPuntosService = app(ConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización en WooCommerce', [
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
            Log::error('Error procesando venta de WooCommerce: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la venta',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function exportarWooCommerce(Request $request)
    {
        // Verificar que el usuario tiene configuración de WooCommerce
        $user = Auth::user();
        $empresa = Empresa::find($user->id_empresa);

        if (
            empty($empresa->woocommerce_api_key) ||
            empty($empresa->woocommerce_store_url) ||
            empty($empresa->woocommerce_consumer_key) ||
            empty($empresa->woocommerce_consumer_secret)
        ) {

            return response()->json([
                'status' => 'error',
                'mensaje' => 'No tienes configurada la integración con WooCommerce'
            ], 400);
        }

        if ($empresa->woocommerce_status != 'connected') {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'La empresa debe estar activa con integración de WooCommerce'
            ], 400);
        }

        if (!$empresa->woocommerceSyncPushesToRemote()) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'En el modo de sincronización actual (WooCommerce → SmartPyme) no se envía catálogo a la tienda. Cámbielo en Mi cuenta, pestaña WooCommerce, si desea exportar a WooCommerce.',
            ], 400);
        }

        // Obtener la sucursal actual del usuario
        $sucursalId = $user->id_bodega;

        // Encolar el trabajo
        ExportProductsToWooCommerce::dispatch($user->id, $sucursalId);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Exportación de productos iniciada. Este proceso puede tomar varios minutos.'
        ]);
    }

    /**
     * Webhook de producto WooCommerce (tema "Product" → creado / actualizado / eliminado).
     * Configurar en WooCommerce la URL: …/api/webhook/woocommerce/{API_KEY}/producto
     */
    public function procesarProductoWooCommerce($tokenEmpresa, Request $request, WooCommerceInboundProductService $inbound)
    {
        if ($request->webhook_id != null) {
            return response()->json(['message' => 'Webhook válido'], 200);
        }

        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)->where('woocommerce_status', 'connected')->first();
        if (!$empresa) {
            Log::error("Woo product webhook: token inválido: {$tokenEmpresa}");
            return response()->json(['status' => 'error', 'mensaje' => 'Token no válido'], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)->where('woocommerce_status', 'connected')->first();
        if (!$usuario) {
            return response()->json(['status' => 'error', 'mensaje' => 'Usuario no encontrado'], 401);
        }

        if (!$empresa->woocommerceSyncAcceptsCatalogFromWoo()) {
            return response()->json([
                'status' => 'skipped',
                'mensaje' => 'Modo de sincronización: no se aceptan productos desde WooCommerce hacia SmartPyme.',
            ], 200);
        }

        $payload = $request->all();
        if (empty($payload) || (empty($payload['id']) && empty($payload['ID']))) {
            return response()->json(['status' => 'skipped', 'mensaje' => 'Payload vacío o sin id'], 200);
        }
        if (isset($payload['ID']) && !isset($payload['id'])) {
            $payload['id'] = $payload['ID'];
        }

        try {
            $result = $inbound->applyPayload($empresa, $usuario, $payload);
            Log::info('WooCommerce producto webhook', [
                'empresa_id' => $empresa->id,
                'result' => $result,
                'woo_id' => $payload['id'] ?? null,
            ]);
            if ($result['action'] === 'error') {
                return response()->json(['status' => 'error', 'detalle' => $result['detail']], 422);
            }
            return response()->json([
                'status' => 'success',
                'action' => $result['action'],
                'producto_id' => $result['producto_id'],
                'detail' => $result['detail'],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Woo product webhook: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'mensaje' => $e->getMessage()], 500);
        }
    }

}
