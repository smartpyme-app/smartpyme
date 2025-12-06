<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\EmpleadoMeta;
use App\Http\Requests\Admin\UsuariosMeta\StoreEmpleadoMetaRequest;

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


    public function store(StoreEmpleadoMetaRequest $request)
    {

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
