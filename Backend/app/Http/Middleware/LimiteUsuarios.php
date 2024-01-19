<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use App\Models\Admin\Empresa;

class LimiteUsuarios
{

    public function handle($request, Closure $next)
    {
        if (!$request->has('id')) {
            $empresa = Empresa::where('id', JWTAuth::parseToken()->authenticate()->id_empresa)->first();

            if($empresa->limiteUsuarios()){
                return  Response()->json(['message' => 'Haz alcanzado el máximo de usuarios', 'code' => 500], 500);
            }
        }

        return $next($request);
    }
}

