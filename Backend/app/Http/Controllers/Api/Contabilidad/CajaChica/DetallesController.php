<?php

namespace App\Http\Controllers\Api\Contabilidad\CajaChica;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\CajaChica\Detalle;
use App\Models\Contabilidad\CajaChica\CajaChica;

class DetallesController extends Controller
{

    public function filter(Request $request) {


        $detalles = Detalle::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->tipo, function($query) use ($request){
                            return $query->where('tipo', $request->tipo);
                        })
                        ->orderBy('id','desc')->get();

        return Response()->json($detalles, 200);

    }
    

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'descripcion'   => 'required|max:255',
            'referencia'    => 'required|max:255',
            'tipo'          => 'required|max:255',
            'total'         => 'required|numeric',
            'usuario_id'    => 'required|numeric',
            'caja_id'       => 'required|numeric',
        ]);

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
            $detalle = new Detalle;
        
        $detalle->fill($request->all());
        $detalle->save();

        $cajachica = CajaChica::find($request->caja_id);
        $cajachica->entradas = $cajachica->detalles()->sum('entrada');
        $cajachica->salidas = $cajachica->detalles()->sum('salida');
        $cajachica->saldo = $cajachica->entradas - $cajachica->salidas;
        $cajachica->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
       
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();

        $cajachica = CajaChica::find($request->caja_id);
        $cajachica->entradas = $cajachica->detalles()->sum('entradas');
        $cajachica->salidas = $cajachica->detalles()->sum('salidas');
        $cajachica->saldo = $cajachica->entradas - $cajachica->salidas;
        $cajachica->save();

        return Response()->json($detalle, 201);

    }


}
