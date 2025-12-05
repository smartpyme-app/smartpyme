<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Impuesto;
use App\Http\Requests\Admin\Impuestos\StoreImpuestoRequest;

class ImpuestosController extends Controller
{
    

    public function index() {
       
        $impuesto = Impuesto::all();

        return Response()->json($impuesto, 200);

    }


    public function read($id) {

        $impuesto = Impuesto::findOrFail($id);
        return Response()->json($impuesto, 200);

    }

    public function store(StoreImpuestoRequest $request)
    {

        if($request->id)
            $impuesto = Impuesto::findOrFail($request->id);
        else
            $impuesto = new Impuesto;

        
        $impuesto->fill($request->all());
        $impuesto->save();

        return Response()->json($impuesto, 200);

    }

    public function delete($id){
        $impuesto = Impuesto::findOrFail($id);
        $impuesto->delete();
        
        return Response()->json($impuesto, 201);

    }


}
