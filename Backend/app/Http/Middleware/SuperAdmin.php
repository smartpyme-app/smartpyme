<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class SuperAdmin
{
    public function handle($request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load('roles');
        // if ($user->id_empresa != 2 && !$user->empresa()->first()->licencia()->first()) {
        //     return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        // }

        if (($user->id_empresa != 2 && $user->id_empresa != 13) && !$user->roles()->where('name', 'super_admin')->exists() && !$user->empresa()->first()->licencia()->first()) {
            return response()->json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        }
            
        return $next($request);
    }
}
