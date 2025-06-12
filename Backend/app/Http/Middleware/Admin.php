<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;

class Admin
{
    public function handle($request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // if ($user->tipo != 'Administrador') {
        //     return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        // }

        if(!$user->roles()->where('name', 'admin')->exists()){
            return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción admin.', 'code' => 403], 403);

        }
            
        return $next($request);
    }
}
