<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Inventario;
use App\Models\Admin\Empresa;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\TrasladosExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade as PDF;


class TrasladosController extends Controller
{   


    public function index(Request $request) {
       
        $traslados = Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->whereHas('origen', function($q) use ($request){
                                    $q->where('id_bodega_de', $request->id_bodega_de);
                                });
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->whereHas('destino', function($q) use ($request){
                                    $q->where('id_bodega', $request->id_bodega_para);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                            })
                            ->when($request->concepto, function($query) use ($request){
                                return $query->where('concepto', 'like', '%' . $request->concepto . '%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden, $request->direccion)
                            ->paginate($request->paginate);


        return Response()->json($traslados, 200);
    }

    public function store(Request $request){

        $request->validate([
          // 'fecha'         => 'required',
          'estado'          => 'required',
          'id_producto'     => 'required',
          'id_bodega_de' => 'required|numeric',
          'id_bodega'     => 'required|numeric',
          'concepto'        => 'required',
          'cantidad'      => 'required|numeric',
          'id_usuario'      => 'required|numeric'
        ]);

        $traslado = new Traslado();
        $traslado->fill($request->all());

        DB::beginTransaction();
         
        try {

        if ($request->id_bodega == $request->id_bodega_de) {
            return  Response()->json(['error' => 'Has seleccionado la misma sucursal.', 'code' => 400], 400);
        }

        $producto = Producto::where('id', $request->id_producto)->with('composiciones')->firstOrFail();
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $request->id_bodega)->first();

        if ($origen->stock < $request->cantidad) {
            return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
        }

        
        if ($origen && $destino) {
            $traslado->save();
            
            $origen->stock -= $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock += $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

        }else{
            return  Response()->json(['error' => 'Una de las sucursales no tiene inventario.', 'code' => 400], 400);
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $request->id_bodega)->first();

            if ($origen->stock < $request->cantidad) {
                return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
            }

            
            if ($origen && $destino) {
                $cantidad = $traslado->cantidad * $comp->cantidad;

                $origen->stock -= $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);

                $destino->stock += $cantidad;
                $destino->save();
                $destino->kardex($traslado, $cantidad);

            }else{
                return  Response()->json(['error' => 'Una de las sucursales no tiene inventario.', 'code' => 400], 400);
            }
        }
      
        DB::commit();
        return Response()->json($traslado, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }
    
    public function delete($id){

        DB::beginTransaction();
         
        try {

        $traslado = Traslado::findOrfail($id);
        $traslado->estado = 'Cancelado';
        $traslado->save();

        $producto = Producto::where('id', $traslado->id_producto)->with('composiciones')->firstOrFail();
        $origen = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_bodega', $traslado->id_bodega)->first();

        // if ($origen->stock < $traslado->cantidad) {
        //     return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
        // }

        
        if ($origen && $destino) {
            $traslado->save();
            
            $origen->stock += $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock -= $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

        }else{
            return  Response()->json(['error' => 'Una de las sucursales no tiene inventario.', 'code' => 400], 400);
        }

        // Composiciones
        foreach ($producto->composiciones as $comp) {
            $producto = Producto::where('id', $comp->id_compuesto)->with('composiciones')->firstOrFail();
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_bodega', $traslado->id_bodega)->first();

            // if ($origen->stock < $traslado->cantidad) {
            //     return  Response()->json(['error' => 'La sucursal no tiene el stock suficiente.', 'code' => 400], 400);
            // }

            
            if ($origen && $destino) {
                $cantidad = $traslado->cantidad * $comp->cantidad;

                $origen->stock += $cantidad;
                $origen->save();
                $origen->kardex($traslado, $cantidad * -1);

                $destino->stock -= $cantidad;
                $destino->save();
                $destino->kardex($traslado, $cantidad);

            }else{
                return  Response()->json(['error' => 'Una de las sucursales no tiene inventario.', 'code' => 400], 400);
            }
        }

        DB::commit();
        return Response()->json($traslado, 201);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function export(Request $request){
        $tralados = new TrasladosExport();
        $tralados->filter($request);

        return Excel::download($tralados, 'tralados.xlsx');
    }

    public function generarPdf($id) {
        $traslado = Traslado::where('id', $id)
            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario'])
            ->firstOrFail();
        
        $empresa = Empresa::findOrFail($traslado->id_empresa);

        $pdf = PDF::loadView('reportes.inventario.traslado-pdf', compact('traslado', 'empresa'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('traslado-' . $traslado->id . '.pdf');
    }

    public function exportarPdf(Request $request) {
        $traslados = Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->where('id_bodega_de', $request->id_bodega_de);
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega_para);
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                            })
                            ->when($request->concepto, function($query) use ($request){
                                return $query->where('concepto', 'like', '%' . $request->concepto . '%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden ?? 'created_at', $request->direccion ?? 'desc')
                            ->with(['producto', 'origen', 'destino', 'empresa', 'usuario'])
                            ->get();
        
        if ($traslados->isEmpty()) {
            return response()->json(['error' => 'No hay traslados para exportar'], 404);
        }

        $empresa = Empresa::findOrFail(Auth::user()->id_empresa);

        // Agrupar traslados por concepto
        $trasladosAgrupados = $traslados->groupBy(function($traslado) {
            return $traslado->concepto ?? 'Sin concepto';
        });

        $pdf = PDF::loadView('reportes.inventario.traslados-pdf', compact('trasladosAgrupados', 'empresa'));
        $pdf->setPaper('letter', 'portrait');
        
        return $pdf->download('traslados-' . date('Y-m-d') . '.pdf');
    }

    public function conceptos() {
        $conceptos = Traslado::select('concepto')
            ->whereNotNull('concepto')
            ->where('concepto', '!=', '')
            ->distinct()
            ->orderBy('concepto', 'asc')
            ->pluck('concepto')
            ->map(function($concepto) {
                return $concepto;
            })
            ->values();

        return Response()->json($conceptos, 200);
    }

}
