<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = JWTAuth::parseToken()->authenticate();

        if ($user->tipo != 'Administrador') {
            return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción.', 'code' => 403], 403);
        }
            
        return $next($request);
    }
}
