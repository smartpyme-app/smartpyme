<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Composicion;

class ComposicionesController extends Controller
{


    public function store(Request $request)
    {
        $request->validate([
            'producto_id'   => 'required|numeric',
            'compuesto_id'  => 'required|numeric',
            'medida'        => 'required|max:255',
            'cantidad'      => 'required|numeric',
        ]);

        if($request->id)
            $composicion = Composicion::findOrFail($request->id);
        else
            $composicion = new Composicion;
        
        $composicion->fill($request->all());
        $composicion->save();

        return Response()->json($composicion, 200);

    }

    public function delete($id)
    {
        $producto = Composicion::findOrFail($id);
        $producto->delete();

        return Response()->json($producto, 201);

    }


}
