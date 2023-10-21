<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;

class IPs
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
        $ips = $user->sucursal()->first()->empresa()->first()->ips;

        if ($ips) {

            if(!in_array($request->getClientIp(), $ips) && $user->tipo != 'Administrador') {
                return  Response()->json(['error' => 'Acceso denegado', 'code' => 403], 403);
            }
        
        }                

        return $next($request);
    }
}

