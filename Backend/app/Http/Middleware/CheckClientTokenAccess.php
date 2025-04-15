<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\Decode;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Token;

class CheckClientTokenAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */


    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $jwt = Decode::jwt_client($token);

        if (!$jwt) {
            return response()->json(['error' => 'token is invalid or has expired.'], 401);
        }
        if (!is_object($jwt) || !property_exists($jwt, 'aud')) {
            return response()->json(['error' => 'Invalid token structure.'], 401);
        }

        $client_id = $jwt->aud;
        $request->merge(['client_id' => $client_id]);

        $token = Token::where('client_id', $client_id)
            ->where('revoked', 0)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();

        if (!$token) {
            return response()->json(['error' => 'token is invalid or has expired.'], 401);
        }

        return $next($request);
    }
}