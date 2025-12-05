<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Canal;
use App\Http\Requests\Admin\Canales\StoreCanalRequest;

class CanalesController extends Controller
{
    

    public function index() {
       
        $canales = Canal::orderBy('nombre', 'asc')->get();

        return Response()->json($canales, 200);

    }
    
    public function list() {
       
        $canales = Canal::where('enable', true)->orderBy('nombre', 'asc')->get();

        return Response()->json($canales, 200);

    }


    public function read($id) {

        $canal = Canal::findOrFail($id);
        return Response()->json($canal, 200);

    }

    public function store(StoreCanalRequest $request)
    {

        if($request->id)
            $canal = Canal::findOrFail($request->id);
        else
            $canal = new Canal;

        
        $canal->fill($request->all());
        $canal->save();

        return Response()->json($canal, 200);

    }

    public function delete($id){
        $canal = Canal::findOrFail($id);
        $canal->delete();
        
        return Response()->json($canal, 201);

    }


}
