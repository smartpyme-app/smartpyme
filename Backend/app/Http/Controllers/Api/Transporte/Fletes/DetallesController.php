<?php

namespace App\Http\Controllers\Api\Transporte\Fletes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transporte\Fletes\Detalle;

class DetallesController extends Controller
{
    


    public function store(Request $request)
    {
        $request->validate([
            'descripcion'       => 'required',
            'tipo_embalaje'     => 'required',
            'unidades'          => 'required',
            'bultos'            => 'required',
            'peso'              => 'required',
            'valor_carga'       => 'required',
            'flete_id'          => 'required'
        ]);
        
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;
        }
        
        $detalle->fill($request->all());
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();

        return Response()->json($detalle, 201);

    }



}
