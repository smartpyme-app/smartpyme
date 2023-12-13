<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;

class SuperAdmin
{
    public function handle($request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->id_empresa != 2) {
            return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        }
            
        return $next($request);
    }
}
