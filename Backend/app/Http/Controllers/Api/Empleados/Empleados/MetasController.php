<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Empleados\Empleados\Meta;

class MetasController extends Controller
{
    

    public function index($id) {
       
        $metas = Meta::where('empleado_id', $id)->get();

        return Response()->json($metas, 200);

    }


    public function read($id) {
        
        $meta = Meta::where('id', $id)->first();

        return Response()->json($meta, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'mes'   => 'required|numeric',
            'ano'   => 'required|numeric',
            'meta'  => 'required|numeric',
            'nota'  => 'sometimes|max:255',
            'empleado_id' => 'required|numeric'
        ]);

        if($request->id)
            $meta = Meta::findOrFail($request->id);
        else
            $meta = new Meta;


        $meta->fill($request->all());
        $meta->save();

        return Response()->json($meta, 200);


    }

    public function delete($id)
    {
       
        $meta = Meta::findOrFail($id);
        $meta->delete();

        return Response()->json($meta, 201);

    }

}
