<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CreateToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        //validar si grant_type no viene en el request crearlo if ternario
        $grant_type = empty($request->grant_type) ? 'client_credentials' : $request->grant_type;
        $request->merge(['grant_type' => $grant_type]);
        $scope = empty($request->scope) ? '*' : $request->scope;
        $request->merge(['scope' => $scope]);
       // return response()->json($request->all());
        return $next($request);
    }
}
