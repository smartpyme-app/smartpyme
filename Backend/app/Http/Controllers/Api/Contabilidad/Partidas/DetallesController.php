<?php

namespace App\Http\Controllers\Api\Contabilidad\Detalles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Detalles\Detalle;

class DetallesController extends Controller
{
    
    public function read($id) {

        $detalle = Detalle::where('id', $id)->firstOrFail();
        return Response()->json($detalle, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'id_cuenta'     => 'required|numeric',
            'id_partida'    => 'required|numeric',
            'concepto'      => 'required|max:255',
            'cargo'         => 'required|numeric',
            'abono'         => 'required|numeric',
        ]);

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
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
