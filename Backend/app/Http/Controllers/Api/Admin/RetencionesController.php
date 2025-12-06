<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Admin\Retencion;
use App\Http\Requests\Admin\Retenciones\StoreRetencionRequest;

class RetencionesController extends Controller
{
    

    public function index() {
       
        $retencion = Retencion::all();

        return Response()->json($retencion, 200);

    }


    public function read($id) {

        $retencion = Retencion::findOrFail($id);
        return Response()->json($retencion, 200);

    }

    public function store(StoreRetencionRequest $request)
    {

        if($request->id)
            $retencion = Retencion::findOrFail($request->id);
        else
            $retencion = new Retencion;

        
        $retencion->fill($request->all());
        $retencion->save();

        return Response()->json($retencion, 200);

    }

    public function delete($id){
        $retencion = Retencion::findOrFail($id);
        $retencion->delete();
        
        return Response()->json($retencion, 201);

    }


}
