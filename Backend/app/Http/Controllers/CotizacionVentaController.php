<?php

namespace App\Http\Controllers;

use App\Models\CotizacionVenta;
use App\Models\CotizacionVentaDetalle;
use App\Services\Ventas\CotizacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreCotizacionVentaRequest;

class CotizacionVentaController extends Controller
{
    public function store(StoreCotizacionVentaRequest $request, CotizacionService $cotizacionService)
    {
        DB::beginTransaction();
        try {
            $cotizacion = $cotizacionService->crearOActualizarCotizacion($request->all());

            if (!$request->id && $request->id_documento) {
                $cotizacionService->asignarCorrelativo($cotizacion, (int) $request->id_documento);
            }

            if ($request->has('detalles') && is_array($request->detalles)) {
                $cotizacionService->guardarDetalles($cotizacion, $request->detalles);
            }

            DB::commit();

            $cotizacion->load('cliente', 'detalles.producto', 'usuario');

            // Misma forma que facturacion/listados FE: el modelo en la raíz
            return response()->json($cotizacion, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $orden = $request->get('orden') ?: 'fecha';
        $direccion = $request->get('direccion') ?: 'desc';
        $paginate = (int) ($request->get('paginate') ?: 10);

        $ordenes = CotizacionVenta::with(
            'cliente:id,nombre,apellido,tipo,nombre_empresa',
            'usuario:id,name',
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
                       ->orWhereHas('cliente', function ($qc) use ($b) {
                           $qc->whereRaw("CONCAT_WS(' ', nombre, apellido) LIKE ?", ["%{$b}%"])
                              ->orWhere('nombre', 'like', "%{$b}%")
                              ->orWhere('apellido', 'like', "%{$b}%")
                              ->orWhere('nombre_empresa', 'like', "%{$b}%");
                       })
                       ->orWhereHas('usuario', function ($qu) use ($b) {
                           $qu->where('name', 'like', "%{$b}%");
                       });
                });
            })
            ->when($request->cliente_nombre, function ($q) use ($request) {
                $b = trim($request->cliente_nombre);
                $q->whereHas('cliente', function ($qc) use ($b) {
                    $qc->whereRaw("CONCAT_WS(' ', nombre, apellido) LIKE ?", ["%{$b}%"]);
                });
            })
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->paginate($paginate);

        return response()->json($ordenes, 200);
    }

    public function read(int $id)
    {
        $cotizacion = CotizacionVenta::with(
            'cliente',
            'usuario',
            'detalles.producto',
            'detalles.customFields.customField',
            'detalles.customFields.customFieldValue'
        )->where('id', $id)->firstOrFail();

        return response()->json($cotizacion, 200);
    }

    public function destroy(int $id)
    {
        $cotizacion = CotizacionVenta::with('detalles.customFields')->findOrFail($id);

        foreach ($cotizacion->detalles as $detalle) {
            if ($detalle->customFields) {
                foreach ($detalle->customFields as $customField) {
                    $customField->delete();
                }
            }
            $detalle->delete();
        }

        $cotizacion->delete();

        return response()->json($cotizacion, 201);
    }

    public function delete($id)
    {
        $detalle = CotizacionVentaDetalle::with('customFields')->findOrFail($id);
        if ($detalle->customFields && count($detalle->customFields) > 0) {
            foreach ($detalle->customFields as $customField) {
                $customField->delete();
            }
        }
        $detalle->delete();

        return response()->json($detalle, 201);
    }
}
