<?php

namespace App\Http\Controllers\Api\Inventario\Composiciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Composiciones\Opcion;

class OpcionesController extends Controller
{


    public function store(Request $request)
    {
        $request->validate([
            'id_producto'   => 'required|numeric',
            'id_composicion'  => 'required|numeric',
        ]);

        if($request->id){
            $opcion = Opcion::findOrFail($request->id);
        }
        else{
            $opcion = new Opcion;

            $existe = Opcion::where('id_producto', $request->id_producto)->where('id_composicion', $request->id_composicion)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido agregado el producto.', 'code' => 400], 400);
        }
        
        $opcion->fill($request->all());
        $opcion->save();

        return Response()->json($opcion, 200);

    }

    public function delete($id)
    {
        $opcion = Opcion::findOrFail($id);
        $opcion->delete();

        return Response()->json($opcion, 201);

    }


}
