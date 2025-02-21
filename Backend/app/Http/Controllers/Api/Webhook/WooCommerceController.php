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

    public function procesarVenta(Request $request)
    {
      //  dd($request->all());
        Log::info('Webhook de WooCommerce recibido', $request->all());

       // try {
            DB::beginTransaction();
            $id_empresa = 234;
            $id_usuario = 664;
            $usuario = User::find($id_usuario);
            //agregar al request el id de la empresa
            $request->merge(['id_empresa' => $id_empresa, 'id_usuario' => $id_usuario,'id_bodega' => $usuario->id_bodega,'id_sucursal' => $usuario->id_sucursal]);

            // 1. Procesar Cliente
            $clienteData = $this->transformer->transformarCliente($request->all());
            $cliente = Cliente::updateOrCreate(
                ['correo' => $clienteData['correo'], 'id_empresa' => $id_empresa],
                $clienteData
            );

            // 2. Crear Venta
            $ventaData = $this->transformer->transformarVenta($request->all(), $cliente->id);
            $venta = Venta::create($ventaData);

            // 3. Procesar detalles y actualizar inventario
            foreach ($request->line_items as $item) {
                $producto = Producto::where('barcode', $item['sku'])->where('id_empresa', $id_empresa)->first();
                
                if (!$producto) {
                   // throw new \Exception("Producto no encontrado: {$item['sku']}");
                   //crear el producto
                   $productoData = $this->transformer->transformarProducto($item, $id_empresa, $id_usuario, $usuario->id_sucursal);
                   $producto = Producto::create($productoData);
                }

                // Crear detalle
                $detalleData = $this->transformer->transformarDetallesVenta($item, $venta->id);
                $detalleData['id_producto'] = $producto->id;
                $venta->detalles()->create($detalleData);

                // Actualizar inventario
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

        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     Log::error('Error procesando venta de WooCommerce: ' . $e->getMessage());

        //     return response()->json([
        //         'status' => 'error',
        //         'mensaje' => 'Error al procesar la venta',
        //         'error' => $e->getMessage()
        //     ], 500);
        // }
    }
}