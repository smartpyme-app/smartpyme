<?php

namespace App\Http\Controllers;

use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CotizacionVentaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            "observaciones" => "required",
            "fecha_expiracion" => "required",
            "fecha" => "required",
            "total" => "required",
            "id_cliente" => "required",
            "id_proyecto" => "required",
            "id_usuario" => "required",
            "id_vendedor" => "required",
            "id_empresa" => "required",
            "id_sucursal" => "required",
            "cobrar_impuestos" => "required|boolean",
            "retencion" => "required|boolean",
            "detalles" => "required|array",
            "detalles.*.cantidad" => "required",
            "detalles.*.precio" => "required",
            "detalles.*.total" => "required",
            "detalles.*.total_costo" => "required",
            "detalles.*.descuento" => "required",
            "detalles.*.no_sujeta" => "required",
            "detalles.*.exenta" => "required",
            "detalles.*.cuenta_a_terceros" => "required",
            "detalles.*.gravada" => "required",
            "detalles.*.iva" => "required",
            "detalles.*.descripcion" => "required",
            "detalles.*.id_producto" => "required",
        ], [
            "observaciones.required" => "Las observaciones son requeridas",
            "fecha_expiracion.required" => "La fecha de expiración es requerida",
            "fecha.required" => "La fecha es requerida",
            "total.required" => "El total es requerido",
            "correlativo.required" => "El correlativo es requerido",
            "id_documento.required" => "El documento es requerido",
            "id_cliente.required" => "El cliente es requerido",
            "id_proyecto.required" => "El proyecto es requerido",
            "id_usuario.required" => "El usuario es requerido",
            "id_vendedor.required" => "El vendedor es requerido",
            "id_empresa.required" => "La empresa es requerida",
            "id_sucursal.required" => "La sucursal es requerida",
            "detalles.required" => "Ingresa por lo menos 1 detalle",

        ]);

        DB::beginTransaction();
        try {
            $cotizacion = new CotizacionVenta();
            $cotizacion->fill($request->merge(["aplicar_retencion" => $request->retencion])->all());
            $cotizacion->save();
            foreach ($request->detalles as $detalle) {
                $newDetalle = CotizacionVentaDetalle::create(
                    [
                        "id_producto" => $detalle["id_producto"],
                        "cantidad" => $detalle["cantidad"],
                        "precio" => $detalle["precio"],
                        "descuento" => $detalle["descuento"],
                        "total" => $detalle["total"],
                        "id_cotizacion_venta" => $cotizacion->id
                    ]
                );
            }


            DB::commit();
            return response()->json(['message' => 'Cotización registrada correctamente', "cotizacion" => $cotizacion], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {

        $ordenes = CotizacionVenta::with(
            "cliente:id,nombre",
            "usuario:id,name",
            'tieneOrdenProduccion'
        )->when($request->inicio, function ($query) use ($request) {
            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
        })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_documento, function ($query) use ($request) {
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->where('tipo_documento', $request->tipo_documento);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->orwhere('correlativo', 'like', '%' . $request->buscador . '%')
                    ->orwhere('estado', 'like', '%' . $request->buscador . '%')
                    ->orwhere('observaciones', 'like', '%' . $request->buscador . '%')
                ;
            })
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);
       

        return Response()->json($ordenes, 200);
    }

    public function read(int $id)
    {
        $cotizacion = CotizacionVenta::with(
            "cliente",
            "usuario",
            "detalles.producto"
        )->where('id', $id)->firstOrFail();
        return response()->json($cotizacion, 200);
    }


    public function delete($id)
    {
        $detalle = CotizacionVentaDetalle::with('customFields')->findOrFail($id);
        if ($detalle->customFields && count($detalle->customFields) > 0) {
            foreach ($detalle->customFields as $customField) {
                $customField->delete();
            }
        }
        // Actualizar inventario
        // $producto = Producto::findOrFail($detalle->producto_id);
        // if ($producto->inventario) {
        //     Inventario::where('bodega_id', $detalle->venta->bodega_id)->where('producto_id', $detalle->producto_id)->increment('stock', $detalle->cantidad);
        // }
        $detalle->delete();

        return Response()->json($detalle, 201);
    }
}
