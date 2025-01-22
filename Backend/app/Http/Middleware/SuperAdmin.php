<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
use Illuminate\Support\Facades\Response;

class SuperAdmin
{
    public function handle($request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();

        

        if ($user->roles()->where('name', 'super_admin')->exists() && !$user->empresa()->first()->licencia()->first()) {
            return Response::json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        }
            
        return $next($request);
    }
}
