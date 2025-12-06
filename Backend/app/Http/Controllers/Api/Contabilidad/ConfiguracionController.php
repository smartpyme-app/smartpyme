<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Configuracion;
use JWTAuth;
use App\Http\Requests\Contabilidad\StoreConfiguracionRequest;

class ConfiguracionController extends Controller
{
    

    public function read($id) {
        
        $configuracion = Configuracion::where('id_empresa', $id)->first();
        return Response()->json($configuracion, 200);

    }


    public function store(StoreConfiguracionRequest $request)
    {

        if($request->id)
            $configuracion = Configuracion::findOrFail($request->id);
        else
            $configuracion = new Configuracion;
        
        $configuracion->fill($request->all());
        $configuracion->save();

        return Response()->json($configuracion, 200);

    }

    public function delete($id)
    {
       
        $configuracion = Configuracion::findOrFail($id);
        $configuracion->delete();

        return Response()->json($configuracion, 201);

    }


}
