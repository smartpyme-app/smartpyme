<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use App\Services\WooCommerceTransformer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WooCommerceController extends Controller
{
    protected $transformer;

    public function __construct(WooCommerceTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    // public function procesarVenta(Request $request)
    public function procesarVenta($tokenUsuario, Request $request)
    {
        Log::info("Webhook recibido para token: {$tokenUsuario}");

        if ($request->webhook_id != null) {
            return response()->json(['message' => 'Webhook válido'], 200);
        }

        $usuario = User::where('woocommerce_api_key', $tokenUsuario)->first();

        if (!$usuario) {
            Log::error("Token de usuario no válido: {$tokenUsuario}");
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Token de acceso no válido'
            ], 401);
        }
        try {
            DB::beginTransaction();

            $request->merge(['id_empresa' => $usuario->id_empresa, 'id_usuario' => $usuario->id, 'id_bodega' => $usuario->id_bodega, 'id_sucursal' => $usuario->id_sucursal]);

            $clienteData = $this->transformer->transformarCliente($request->all());
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $usuario->id_empresa],
                $clienteData
            );

            // 2. Crear Venta
            $ventaData = $this->transformer->transformarVenta($request->all(), $cliente->id);
            $venta = Venta::create($ventaData);

            // 3. Procesar detalles y actualizar inventario
            foreach ($request->line_items as $item) {
                $producto = Producto::where('codigo', $item['sku'])->where('id_empresa', $usuario->id_empresa)->first();

                if (!$producto) {
                    // throw new \Exception("Producto no encontrado: {$item['sku']}");
                    //crear el producto
                    $productoData = $this->transformer->transformarProducto($item, $usuario->id_empresa, $usuario->id, $usuario->id_sucursal);
                    $producto = Producto::create($productoData);
                }

                // Crear detalle
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
}
