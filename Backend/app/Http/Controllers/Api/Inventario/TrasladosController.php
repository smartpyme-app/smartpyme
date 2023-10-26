<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Traslado;
use App\Models\Inventario\Inventario;


use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrasladosController extends Controller
{   


    public function index(){

        $traslados = Traslado::orderBy('id', 'DESC')->paginate(10);

        return Response()->json($traslados, 200);
    }

    public function store(Request $request){

        $request->validate([
          // 'fecha'         => 'required',
          // 'estado'        => 'required',
          'id_producto'        => 'required',
          'id_sucursal_de'     => 'required|numeric',
          'id_sucursal'    => 'required|numeric',
          'concepto'      => 'required',
          // 'detalles'     => 'required',
          // 'usuario_id'    => 'required|numeric'
        ]);

        $traslado = new Traslado();
        $traslado->fill($request->all());

        // Disminuir origen
        $origen = Inventario::where('id_producto', $request->id_producto)->where('id_sucursal', $request->id_sucursal_de)->first();
        $destino = Inventario::where('id_producto', $request->id_producto)->where('id_sucursal', $request->id_sucursal)->first();

        if ($origen->id_sucursal == $destino->id_sucursal) {
            return  Response()->json(['error' => 'Has seleccionado la misma sucursal.', 'code' => 400], 400);
        }
        if ($origen && $destino) {
            $origen->stock -= $traslado->cantidad;
            $origen->save();
            $origen->kardex($traslado, $traslado->cantidad * -1);


            $destino->stock += $traslado->cantidad;
            $destino->save();
            $destino->kardex($traslado, $traslado->cantidad);

        }else{
            return  Response()->json(['error' => 'Una de las sucursales no tiene inventario.', 'code' => 400], 400);
        }
      
      $traslado->save();

      return redirect('traslados')->with(['message' => 'Traslado guardado correctamente.', 'alert' => 'alert-success']);

    }
    
     public function update($id){

      DB::beginTransaction();
      $traslado = Traslado::findOrfail($id);
      try {
        $traslado->estado = 'Cancelado';
        $traslado->save();

        $inventario = Inventario::where('id_producto', $traslado->id_producto)
                                  ->where('id_sucursal', $traslado->id_sucursal)->first();

        $origen = Inventario::where('id_producto', $inventario->id_producto)->where('id_sucursal', $traslado->id_sucursal_de)->first();
        $destino = Inventario::where('id_producto', $inventario->id_producto)->where('id_sucursal', $traslado->id_sucursal)->first();
        if ($origen && $destino) {
          $origen->stock += $traslado->cantidad;
          $origen->save();
          $origen->kardex($traslado, $traslado->cantidad * -1);
          
          $destino->stock -= $traslado->cantidad;
          $destino->save();
          $destino->kardex($traslado, $traslado->cantidad);
        }else{
          return redirect('traslados')->with(['message' => 'Una de las sucursales no tiene inventario.', 'alert' => 'alert-warning']);
        }

        DB::commit();

        return redirect('traslados')->with(['message' => 'Traslado cancelado correctamente.', 'alert' => 'alert-warning']);
      } catch (Exception $e) {
          DB::rollBack(); // Tell Laravel, "It's not you, it's me. Please don't persist to DB"
            return response([
                'message' => $e->getMessage(),
                'status' => 'failed'
            ], 400);
      }
    }

}
