<?php

namespace App\Http\Controllers\Api\Inventario\Salidas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Salidas\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;

class DetallesController extends Controller
{
    

    public function index() {

        $detalles = Detalle::orderBy('id','desc')->paginate(7);

        return Response()->json($detalles, 200);

    }


    public function read($id) {

        $detalle = Detalle::findOrFail($id);
        return Response()->json($detalle, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required',
            'cantidad'          => 'required',
            'costo'             => 'required',
            'total'             => 'required',
            'id_salida'       => 'required'
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
