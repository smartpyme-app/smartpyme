<?php

namespace App\Http\Controllers;

use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreCotizacionVentaRequest;

class CotizacionVentaController extends Controller
{
    public function store(StoreCotizacionVentaRequest $request)
    {

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
        //Log::info('test');

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
            ->when($request->buscador, function ($q) use ($request) {
                $b = trim($request->buscador);
                $q->where(function ($qq) use ($b) {
                    $qq->where('correlativo', 'like', "%{$b}%")
                       ->orWhere('estado', 'like', "%{$b}%")
                       ->orWhere('observaciones', 'like', "%{$b}%")
                       // cliente: nombre + apellido
                       ->orWhereHas('cliente', function ($qc) use ($b) {
                           $qc->whereRaw("CONCAT_WS(' ', nombre, apellido) LIKE ?", ["%{$b}%"])
                              ->orWhere('nombre', 'like', "%{$b}%")
                              ->orWhere('apellido', 'like', "%{$b}%");
                       })
                       // usuario: name
                       ->orWhereHas('usuario', function ($qu) use ($b) {
                           $qu->where('name', 'like', "%{$b}%");
                       })
                       // empresa: nombre
                       ->orWhereHas('cliente', function ($qe) use ($b) {
                           $qe->where('nombre_empresa', 'like', "%{$b}%");
                       });
                });
            })
            ->when($request->cliente_nombre, function ($q) use ($request) {
                $b = trim($request->cliente_nombre);
                $q->whereHas('cliente', function ($qc) use ($b) {
                    $qc->whereRaw("CONCAT_WS(' ', nombre, apellido) LIKE ?", ["%{$b}%"]);
                });
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
