<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Traslado;
use App\Models\Inventario\Inventario;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\TrasladosExport;
use Maatwebsite\Excel\Facades\Excel;


class TrasladosController extends Controller
{   


    public function index(Request $request) {
       
        $traslados = Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_sucursal_de, function($query) use ($request){
                                return $query->whereHas('origen', function($q) use ($request){
                                    $q->where('id_sucursal_de', $request->id_sucursal_de);
                                });
                            })
                            ->when($request->id_sucursal_para, function($query) use ($request){
                                return $query->whereHas('destino', function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal_para);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                });
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
          'id_sucursal_de' => 'required|numeric',
          'id_sucursal'     => 'required|numeric',
          'concepto'        => 'required',
          'cantidad'      => 'required|numeric',
          'id_usuario'      => 'required|numeric'
        ]);

        $traslado = new Traslado();
        $traslado->fill($request->all());

        DB::beginTransaction();
         
        try {

        if ($request->id_sucursal == $request->id_sucursal_de) {
            return  Response()->json(['error' => 'Has seleccionado la misma sucursal.', 'code' => 400], 400);
        }

        $producto = Producto::where('id', $request->id_producto)->with('composiciones')->firstOrFail();
        $origen = Inventario::where('id_producto', $producto->id)->where('id_sucursal', $request->id_sucursal_de)->first();
        $destino = Inventario::where('id_producto', $producto->id)->where('id_sucursal', $request->id_sucursal)->first();

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
            $origen = Inventario::where('id_producto', $comp->id_compuesto)->where('id_sucursal', $request->id_sucursal_de)->first();
            $destino = Inventario::where('id_producto', $comp->id_compuesto)->where('id_sucursal', $request->id_sucursal)->first();

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

        $traslado = Traslado::findOrfail($id);
        $traslado->estado = 'Cancelado';
        $traslado->save();

        $origen = Inventario::where('id_producto', $traslado->id_producto)->where('id_sucursal', $traslado->id_sucursal_de)->first();
        $destino = Inventario::where('id_producto', $traslado->id_producto)->where('id_sucursal', $traslado->id_sucursal)->first();
        
        if ($origen && $destino) {
            $origen->stock += $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);

            $destino->stock -= $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);
        }else{
            return  Response()->json(['error' => 'Una de las sucursales no tiene inventario', 'code' => 400], 400);
        }

        return Response()->json($traslado, 201);

    }

    public function export(Request $request){
        $tralados = new TrasladosExport();
        $tralados->filter($request);

        return Excel::download($tralados, 'tralados.xlsx');
    }

}
