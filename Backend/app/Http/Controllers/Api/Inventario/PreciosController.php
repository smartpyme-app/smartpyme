<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventario\Precio;

class PreciosController extends Controller
{


    public function store(Request $request)
    {
        $request->validate([
            'precio'  => 'required|numeric',
            'producto_id'   => 'required|numeric',
        ]);

        if($request->id)
            $precio = Precio::findOrFail($request->id);
        else
            $precio = new Precio;
        
        $precio->fill($request->all());
        $precio->save();

        return Response()->json($precio, 200);

    }

    public function delete($id)
    {
        $precio = Precio::findOrFail($id);
        $precio->delete();

        return Response()->json($precio, 201);

    }


}
