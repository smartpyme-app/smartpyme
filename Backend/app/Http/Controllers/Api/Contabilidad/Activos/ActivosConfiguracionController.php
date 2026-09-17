<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\StoreActivoConfiguracionRequest;
use App\Models\Contabilidad\ActivoConfiguracion;
use JWTAuth;

class ActivosConfiguracionController extends Controller
{
    public function show()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        return response()->json(ActivoConfiguracion::forEmpresa($usuario->id_empresa), 200);
    }

    public function store(StoreActivoConfiguracionRequest $request)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $config = ActivoConfiguracion::forEmpresa($usuario->id_empresa);

        $config->fill($request->validated());
        $config->frecuencia = 'mensual';
        $config->save();

        return response()->json($config, 200);
    }
}
