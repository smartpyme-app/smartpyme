<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Acceso;
use App\Http\Requests\Admin\Accesos\StoreAccesoRequest;

class AccesosController extends Controller
{
    

    public function index() {
       
        $accesos = Acceso::orderBy('id','desc')->paginate(10);

        return Response()->json($accesos, 200);

    }


    public function read($id) {

        $acceso = Acceso::findOrFail($id);
        return Response()->json($acceso, 200);

    }

    public function store(StoreAccesoRequest $request)
    {

        if($request->id)
            $acceso = Acceso::findOrFail($request->id);
        else
            $acceso = new Acceso;

        
        $acceso->fill($request->all());
        $acceso->save();

        return Response()->json($acceso, 200);

    }

    public function delete($id){
        $acceso = Acceso::findOrFail($id);
        $acceso->delete();
        
        return Response()->json($acceso, 201);

    }


}
