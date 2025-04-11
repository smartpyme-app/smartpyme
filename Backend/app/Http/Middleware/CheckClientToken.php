<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Passport\Passport;
use \Firebase\JWT\JWT;
use App\Helpers\Decode;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

class CheckClientToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->header('Authorization')) {
            return response()->json(['error' => 'Token not found'], 401);
        }

        return $next($request);
    }
}