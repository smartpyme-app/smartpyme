<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Banco;

class BancosController extends Controller
{
    

    public function index() {
       
        $banco = Banco::all();

        return Response()->json($banco, 200);

    }


    public function read($id) {

        $banco = Banco::findOrFail($id);
        return Response()->json($banco, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'        => 'required|max:255',
            'empresa_id'       => 'required|numeric'
        ]);

        if($request->id)
            $banco = Banco::findOrFail($request->id);
        else
            $banco = new Banco;

        
        $banco->fill($request->all());
        $banco->save();

        return Response()->json($banco, 200);

    }

    public function delete($id){
        $banco = Banco::findOrFail($id);
        $banco->delete();
        
        return Response()->json($banco, 201);

    }


}
