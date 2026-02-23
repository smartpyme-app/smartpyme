<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\KardexMasivoQueue;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Inventario\KardexExport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class KardexController extends Controller
{


    public function index(Request $request)
    {

        $producto = Producto::where('id', $request->id_producto)->with('inventarios')->firstOrFail();

        $kardex = Kardex::where('id_producto', $producto->id)
            ->when($request->id_inventario, function ($q) use ($request) {
                $q->where('id_inventario', $request->id_inventario);
            })
            ->when($request->inicio, function ($q) use ($request) {
                $q->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($q) use ($request) {
                $q->where('fecha', '<=', $request->fin);
            })
            ->when($request->detalle, function ($q) use ($request) {
                return $q->where('detalle', 'like', '%' . $request->detalle . '%');
            })
            ->orderBy($request->orden ?? 'fecha', $request->direccion ?? 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Filtrar por lote_id si se proporciona
        if ($request->lote_id) {
            $lote = Lote::find($request->lote_id);
            if ($lote) {
                $kardex = $kardex->filter(function ($movimiento) use ($lote, $producto) {
                    // Obtener el lote_id del movimiento usando la misma lógica que getNumeroLoteAttribute
                    $loteIdMovimiento = $this->obtenerLoteIdDelMovimiento($movimiento, $producto->id);
                    return $loteIdMovimiento == $lote->id;
                })->values();
            } else {
                $kardex = collect([]);
            }
        }


        $producto->movimientos = $kardex;

        return Response()->json($producto, 200);
    }


    public function read($id)
    {

        $kardex = Kardex::findOrFail($id);
        return Response()->json($kardex, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'id_producto'   => 'required',
            'id_sucursal' => 'required|numeric',
            'detalle'       => 'required',
            'referencia'    => 'sometimes|max:255',
            'entrada_cantidad'      => 'required|numeric',
            'entrada_valor'         => 'required|numeric',
            'salida_cantidad'      => 'required|numeric',
            'salida_valor'         => 'required|numeric',
            'total_cantidad'      => 'required|numeric',
            'total_valor'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
        ]);

        if ($request->id)
            $kardex = Kardex::findOrFail($request->id);
        else
            $kardex = new Kardex;

        // Actualizar inventario
        $producto = Producto::withoutGlobalScopes()->findOrFail($request->id_producto);
        $inventario = Inventario::where('id', $request->id_sucursal)->where('id_producto', $producto->id)->first();
        $inventario->stock += ($request->stock_final - $request->stock_inicial);
        $inventario->save();

        $kardex->fill($request->all());
        $kardex->save();

        return Response()->json($kardex, 200);
    }

    public function delete($id)
    {
        $kardex = Kardex::findOrFail($id);
        $kardex->delete();

        return Response()->json($kardex, 201);
    }


    public function search($txt)
    {

        $kardexs = Kardex::whereHas('producto', function ($query) use ($txt) {
            $query->where('nombre', 'like', '%' . $txt . '%')
                ->orWhere('codigo', 'like', '%' . $txt . '%');
        })
            ->orwhereHas('bodega', function ($query) use ($txt) {
                $query->where('nombre', 'like', '%' . $txt . '%');
            })
            ->paginate(10);

        return Response()->json($kardexs, 200);
    }

    public function export(Request $request)
    {
        $kardex = new KardexExport();
        $kardex->filter($request);

        return Excel::download($kardex, 'kardex.xlsx');
    }

    public function exportFiltrado(Request $request)
    {
        try {
            $kardex = new \App\Exports\Inventario\KardexFiltradoExport();
            $kardex->filter($request);

            return Excel::download($kardex, 'kardex-filtrado.xlsx');
        } catch (\Exception $e) {
            Log::error('Error al exportar kardex filtrado: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return Response()->json(['error' => 'No se pudo exportar el kardex filtrado. Verifica que los datos sean correctos.'], 500);
        }
    }

    public function solicitarMasivo(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'id_empresa' => 'required|integer'
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
            'id_empresa.required' => 'El ID de empresa es obligatorio.'
        ]);

        try {
            // Crear registro en la cola para procesamiento en segundo plano
            $queueItem = KardexMasivoQueue::create([
                'email' => $request->email,
                'id_empresa' => $request->id_empresa,
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Solicitud de kardex masivo registrada. Recibirá un correo electrónico cuando esté listo.',
                'success' => true,
                'queue_id' => $queueItem->id
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al solicitar kardex masivo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar la solicitud. Intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function estadoCola(Request $request)
    {
        $request->validate([
            'id_empresa' => 'required|integer'
        ]);

        try {
            $estados = KardexMasivoQueue::where('id_empresa', $request->id_empresa)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'email', 'status', 'created_at', 'started_at', 'completed_at', 'error_message']);

            return response()->json([
                'success' => true,
                'estados' => $estados
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de cola: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener estado de cola.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el lote_id de un movimiento del kardex
     */
    private function obtenerLoteIdDelMovimiento($movimiento, $idProducto)
    {
        // Si es un ajuste
        if (strpos($movimiento->detalle, 'Ajuste') !== false || strpos($movimiento->detalle, 'ajuste') !== false) {
            $ajuste = \App\Models\Inventario\Ajuste::find($movimiento->referencia);
            if ($ajuste && $ajuste->lote_id) {
                return $ajuste->lote_id;
            }
        }
        
        // Si es un traslado
        if (strpos($movimiento->detalle, 'Traslado') !== false || strpos($movimiento->detalle, 'traslado') !== false) {
            $traslado = \App\Models\Inventario\Traslado::find($movimiento->referencia);
            if ($traslado && $traslado->lote_id) {
                return $traslado->lote_id;
            }
        }
        
        // Si es una venta
        if (in_array($movimiento->detalle, ['Venta', 'Venta a consigna', 'Venta Anulada'])) {
            $detalleVenta = \App\Models\Ventas\Detalle::where('id_venta', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleVenta && $detalleVenta->lote_id) {
                return $detalleVenta->lote_id;
            }
        }
        
        // Si es una compra
        if (in_array($movimiento->detalle, ['Compra', 'Compra a consigna', 'Compra Anulada'])) {
            $detalleCompra = \App\Models\Compras\Detalle::where('id_compra', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleCompra && $detalleCompra->lote_id) {
                return $detalleCompra->lote_id;
            }
        }
        
        // Si es una devolución de venta
        if (strpos($movimiento->detalle, 'Devolución Venta') !== false) {
            $detalleDevolucion = \App\Models\Ventas\Devoluciones\Detalle::where('id_devolucion', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleDevolucion && $detalleDevolucion->lote_id) {
                return $detalleDevolucion->lote_id;
            }
        }
        
        // Si es una devolución de compra
        if (strpos($movimiento->detalle, 'Devolución Compra') !== false) {
            $detalleDevolucion = \App\Models\Compras\Devoluciones\Detalle::where('id_devolucion', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleDevolucion && $detalleDevolucion->lote_id) {
                return $detalleDevolucion->lote_id;
            }
        }
        
        // Si es otra entrada
        if (in_array($movimiento->detalle, ['Otra Entrada', 'Otra Entrada Anulada'])) {
            $detalleEntrada = \App\Models\Inventario\Entradas\Detalle::where('id_entrada', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleEntrada && $detalleEntrada->lote_id) {
                return $detalleEntrada->lote_id;
            }
        }
        
        // Si es otra salida
        if (in_array($movimiento->detalle, ['Otra Salida', 'Otra Salida Anulada'])) {
            $detalleSalida = \App\Models\Inventario\Salidas\Detalle::where('id_salida', $movimiento->referencia)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleSalida && $detalleSalida->lote_id) {
                return $detalleSalida->lote_id;
            }
        }
        
        return null;
    }
}
