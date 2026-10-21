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
use Illuminate\Http\Request;
use App\Services\WooCommerceTransformer;
use App\Services\WooCommerceInboundProductService;
use App\Services\WooCommerceOrderExtrasService;
use App\Services\WooCommerceSpExportCatalogService;
use App\Services\WooCommerceProductLineMatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FidelizacionCliente\ConsumoPuntosService;

class WooCommerceController extends Controller
{
    protected $transformer;
    protected $orderExtrasService;

    /** @var WooCommerceProductLineMatcher */
    protected $lineMatcher;

    public function __construct(
        WooCommerceTransformer $transformer,
        WooCommerceOrderExtrasService $orderExtrasService,
        WooCommerceProductLineMatcher $lineMatcher
    ) {
        $this->transformer = $transformer;
        $this->orderExtrasService = $orderExtrasService;
        $this->lineMatcher = $lineMatcher;
    }

    public function procesarVenta($tokenEmpresa, Request $request)
    {
        Log::info("Webhook recibido para token: {$tokenEmpresa}");

        if ($request->webhook_id != null) {
            return response()->json(['message' => 'Webhook válido'], 200);
        }

        $topic = strtolower((string) $request->header('X-WC-Webhook-Topic', 'order.created'));
        if ($topic === 'order.updated') {
            return $this->procesarVentaActualizadaWoo($tokenEmpresa, $request);
        }
        if ($topic === 'order.deleted') {
            return $this->procesarVentaCanceladaWoo($tokenEmpresa, $request);
        }

        return $this->crearVentaDesdePedidoWoo($tokenEmpresa, $request);
    }

    private function crearVentaDesdePedidoWoo($tokenEmpresa, Request $request)
    {
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
                $producto = $this->lineMatcher->findForOrderLine($usuario->id_empresa, $item);

                if (!$producto) {
                    $productoData = $this->transformer->transformarProductoDesdeLineItem(
                        $item,
                        $usuario->id_empresa,
                        $usuario->id,
                        $usuario->id_sucursal
                    );
                    $producto = Producto::create($productoData);
                    $wooVariationId = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
                    $wooProductId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                    if ($wooVariationId > 0) {
                        $producto->woocommerce_id = $wooVariationId;
                        $producto->woocommerce_parent_id = $wooProductId > 0 ? $wooProductId : null;
                        $producto->saveQuietly();
                    } elseif ($wooProductId > 0) {
                        $producto->woocommerce_id = $wooProductId;
                        $producto->saveQuietly();
                    }
                    Log::info('WooCommerce: producto creado desde line_item de pedido', [
                        'producto_id' => $producto->id,
                        'codigo' => $producto->codigo,
                        'woo_order_id' => $wooOrderId,
                    ]);
                } elseif (!empty($item['variation_id']) && empty($producto->woocommerce_id)) {
                    $producto->woocommerce_id = (int) $item['variation_id'];
                    $producto->woocommerce_parent_id = !empty($item['product_id'])
                        ? (int) $item['product_id']
                        : $producto->woocommerce_parent_id;
                    $producto->saveQuietly();
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

            $shippingLines = $request->shipping_lines ?? $request->input('shipping_lines', []);
            if (!empty($shippingLines)) {
                $this->orderExtrasService->procesarEnvios(
                    $shippingLines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );
            }

            $feeLines = $request->fee_lines ?? $request->input('fee_lines', []);
            if (!empty($feeLines)) {
                $this->orderExtrasService->procesarRecargos(
                    $feeLines,
                    $venta->id,
                    $usuario->id_empresa,
                    $usuario->id,
                    $usuario->id_sucursal
                );
            }

            $venta->load('detalles');
            $this->recalcularTotalesVenta($venta);

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

        $sucursalId = $user->id_bodega;
        $soloConStock = filter_var($request->input('solo_con_stock', true), FILTER_VALIDATE_BOOLEAN);

        ExportProductsToWooCommerce::dispatch($user->id, $sucursalId, $soloConStock);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Exportación de productos iniciada. Este proceso puede tomar varios minutos.',
            'solo_con_stock' => $soloConStock,
        ]);
    }

    public function vistaPreviaExportacionWoo(Request $request, WooCommerceSpExportCatalogService $catalog)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::find($user->id_empresa);
        if (!$empresa || $empresa->woocommerce_status !== 'connected') {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'WooCommerce no está conectado',
            ], 400);
        }

        $soloConStock = filter_var($request->input('solo_con_stock', true), FILTER_VALIDATE_BOOLEAN);
        $bodegaId = (int) $user->id_bodega;
        $productos = $catalog->productosElegibles($user, $bodegaId, $soloConStock);
        $stocks = $catalog->stocksPorBodega($productos, $bodegaId);
        $grupos = $catalog->buildPreviewGroups($productos, $stocks);

        $variables = 0;
        $simples = 0;
        foreach ($grupos as $g) {
            if ($g['tipo'] === 'variable') {
                $variables++;
            } else {
                $simples++;
            }
        }

        return response()->json([
            'status' => 'success',
            'solo_con_stock' => $soloConStock,
            'totales' => [
                'productos_sp' => $productos->count(),
                'grupos' => count($grupos),
                'variables' => $variables,
                'simples' => $simples,
            ],
            'grupos' => $grupos,
        ]);
    }

    public function obtenerEstadoExportacion(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $empresa = Empresa::find($user->id_empresa);
        if (!$empresa) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        $cache = Cache::get('woocommerce_export_' . $empresa->id, []);
        $syncStatus = $empresa->woocommerce_sync_status;
        $estado = 'inactivo';
        if ($syncStatus === 'syncing') {
            $estado = 'procesando';
        } elseif ($syncStatus === 'completed') {
            $estado = 'completado';
        } elseif ($syncStatus === 'error') {
            $estado = 'error';
        }

        return response()->json(array_merge([
            'estado' => $cache['estado'] ?? $estado,
            'progreso' => (int) ($empresa->woocommerce_sync_progress ?? 0),
            'lotes_total' => (int) ($empresa->woocommerce_sync_total_batches ?? 0),
            'lotes_procesados' => (int) ($empresa->woocommerce_sync_processed_batches ?? 0),
            'ultima_sync' => $empresa->woocommerce_last_sync,
            'mensaje' => $empresa->woocommerce_error ?: ($cache['mensaje'] ?? 'No hay exportación activa.'),
            'creados' => 0,
            'actualizados' => 0,
            'errores' => 0,
        ], $cache));
    }

    private function procesarVentaActualizadaWoo($tokenEmpresa, Request $request)
    {
        $contexto = $this->resolverContextoPedidoWoo($tokenEmpresa);
        if ($contexto instanceof \Illuminate\Http\JsonResponse) {
            return $contexto;
        }
        /** @var Empresa $empresa */
        $empresa = $contexto['empresa'];

        $wooOrderId = $request->input('id');
        $referencia = $wooOrderId ? 'WOOC-' . $wooOrderId : null;
        if (!$referencia) {
            return response()->json(['status' => 'skipped', 'mensaje' => 'Pedido sin id'], 200);
        }

        $venta = Venta::withoutGlobalScope('empresa')
            ->where('referencia_woocommerce', $referencia)
            ->where('id_empresa', $empresa->id)
            ->first();

        $wooStatus = (string) $request->input('status', '');
        if ($this->transformer->esEstadoPedidoAnulado($wooStatus)) {
            return $this->anularVentaWooCommerce($venta, $wooOrderId, 'Pedido actualizado/cancelado en WooCommerce');
        }

        if (!$venta) {
            return $this->crearVentaDesdePedidoWoo($tokenEmpresa, $request);
        }

        if ($this->ventaEmitida($venta)) {
            return response()->json([
                'status' => 'ignored',
                'mensaje' => 'Venta ya emitida en SmartPyme - no se modifica desde WooCommerce',
                'venta_id' => $venta->id,
            ], 200);
        }

        $nuevoEstado = $this->transformer->mapearEstado($wooStatus);
        $venta->update([
            'estado' => $nuevoEstado,
            'observaciones' => trim(($venta->observaciones ? $venta->observaciones . ' | ' : '')
                . 'Actualizado desde WooCommerce ' . now()->format('d/m/Y H:i:s')),
        ]);

        return response()->json([
            'status' => 'success',
            'mensaje' => 'Venta actualizada',
            'venta_id' => $venta->id,
            'estado' => $nuevoEstado,
        ], 200);
    }

    private function procesarVentaCanceladaWoo($tokenEmpresa, Request $request)
    {
        $contexto = $this->resolverContextoPedidoWoo($tokenEmpresa);
        if ($contexto instanceof \Illuminate\Http\JsonResponse) {
            return $contexto;
        }
        /** @var Empresa $empresa */
        $empresa = $contexto['empresa'];

        $wooOrderId = $request->input('id');
        $referencia = $wooOrderId ? 'WOOC-' . $wooOrderId : null;
        $venta = $referencia
            ? Venta::withoutGlobalScope('empresa')
                ->where('referencia_woocommerce', $referencia)
                ->where('id_empresa', $empresa->id)
                ->first()
            : null;

        return $this->anularVentaWooCommerce($venta, $wooOrderId, 'Pedido eliminado en WooCommerce');
    }

    /**
     * @return array{empresa: Empresa, usuario: User}|\Illuminate\Http\JsonResponse
     */
    private function resolverContextoPedidoWoo(string $tokenEmpresa)
    {
        $empresa = Empresa::where('woocommerce_api_key', $tokenEmpresa)->where('woocommerce_status', 'connected')->first();
        if (!$empresa) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido o no conectado',
            ], 401);
        }

        $usuario = User::where('id_empresa', $empresa->id)->where('woocommerce_status', 'connected')->first();
        if (!$usuario) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Usuario no encontrado',
            ], 401);
        }

        return ['empresa' => $empresa, 'usuario' => $usuario];
    }

    private function anularVentaWooCommerce(?Venta $venta, $wooOrderId, string $motivo)
    {
        if (!$venta) {
            Log::warning('WooCommerce: venta no encontrada para anulación', ['woo_order_id' => $wooOrderId]);
            return response()->json([
                'status' => 'warning',
                'mensaje' => 'Venta no encontrada para el pedido',
            ], 404);
        }

        if ($venta->estado === 'Anulada') {
            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta ya estaba anulada',
                'venta_id' => $venta->id,
            ], 200);
        }

        if ($this->ventaEmitida($venta)) {
            return response()->json([
                'status' => 'ignored',
                'mensaje' => 'Venta ya emitida en SmartPyme - no se anula desde WooCommerce',
                'venta_id' => $venta->id,
            ], 200);
        }

        try {
            DB::beginTransaction();

            $venta->update([
                'estado' => 'Anulada',
                'observaciones' => trim(($venta->observaciones ? $venta->observaciones . ' | ' : '') . $motivo . ' ' . now()->format('d/m/Y H:i:s')),
            ]);

            $venta->load('detalles.producto');
            foreach ($venta->detalles as $detalle) {
                $producto = $detalle->producto;
                if (!$producto) {
                    continue;
                }
                $inventario = Inventario::where('id_producto', $producto->id)
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();
                if (!$inventario) {
                    continue;
                }
                $cantidad = is_numeric($detalle->cantidad) ? (float) $detalle->cantidad : 0;
                if ($cantidad <= 0) {
                    continue;
                }
                $inventario->increment('stock', $cantidad);
                $precio = is_numeric($detalle->precio) ? (float) $detalle->precio : 0;
                $costoProducto = is_numeric($producto->costo) ? (float) $producto->costo : 0;
                $inventario->kardex($venta, -$cantidad, $precio, $costoProducto, null, ['origen' => 'woocommerce']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'mensaje' => 'Venta anulada correctamente',
                'venta_id' => $venta->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error anulando venta WooCommerce: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al anular la venta',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function ventaEmitida(Venta $venta): bool
    {
        return !empty($venta->sello_mh);
    }

    private function recalcularTotalesVenta(Venta $venta): void
    {
        $subtotal = 0;
        $iva = 0;
        $gravada = 0;
        $exenta = 0;

        foreach ($venta->detalles as $detalle) {
            $subtotal += round($detalle->cantidad * $detalle->precio, 2);
            $iva += round($detalle->iva, 2);
            $gravada += round($detalle->gravada, 2);
            $exenta += round($detalle->exenta ?? 0, 2);
        }

        $total = round($gravada + $iva + $exenta, 2);

        $venta->update([
            'sub_total' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'gravada' => round($gravada, 2),
            'exenta' => round($exenta, 2),
            'total' => $total,
            'monto_pago' => $total,
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
