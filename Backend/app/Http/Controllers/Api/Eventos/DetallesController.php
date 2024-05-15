<?php

namespace App\Http\Controllers\Api\Eventos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Eventos\Detalle;

class DetallesController extends Controller
{
    
    public function store(Request $request)
    {
        $request->validate([
            'id_producto'    => 'required',
            'cantidad'    => 'required',
            'id_evento'    => 'required'
        ]);
        
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

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
