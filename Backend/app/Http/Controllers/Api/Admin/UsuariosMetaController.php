<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\EmpleadoMeta;

class UsuariosMetaController extends Controller
{
    

    public function index() {
       
        $metas = EmpleadoMeta::orderBy('id','desc')->paginate(7);

        return Response()->json($metas, 200);

    }


    public function read($id) {
        
        $meta = EmpleadoMeta::where('id', $id)->first();

        return Response()->json($meta, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'mes'   => 'required|numeric',
            'ano'   => 'required|numeric',
            'meta'  => 'required|numeric',
            'usuario_id' => 'required|numeric'
        ]);

        if($request->id)
            $meta = EmpleadoMeta::findOrFail($request->id);
        else
            $meta = new EmpleadoMeta;


        $meta->fill($request->all());
        $meta->save();

        return Response()->json($meta, 200);


    }

    public function delete($id)
    {
       
        $meta = EmpleadoMeta::findOrFail($id);
        $meta->delete();

        return Response()->json($meta, 201);

    }

}
