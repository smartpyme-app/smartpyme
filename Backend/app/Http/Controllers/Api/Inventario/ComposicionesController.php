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
            'id_producto'   => 'required|numeric',
            'id_compuesto'  => 'required|numeric',
            // 'medida'        => 'required|max:255',
            'cantidad'      => 'required|numeric',
        ]);

        if($request->id)
            $composicion = Composicion::findOrFail($request->id);
        else
            $composicion = new Composicion;
        
        $composicion->fill($request->all());
        $composicion->save();

        $composicion = Composicion::with('compuesto')->where('id', $composicion->id)->firstOrFail();


        return Response()->json($composicion, 200);

    }

    public function delete($id)
    {
        $producto = Composicion::findOrFail($id);
        $producto->delete();

        return Response()->json($producto, 201);

    }


}
