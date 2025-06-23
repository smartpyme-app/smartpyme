<?php

namespace App\Http\Middleware\Authorization;

use Closure;

class EnsureCompanyScope
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asignada'], 403);
        }

        return $next($request);
    }
}