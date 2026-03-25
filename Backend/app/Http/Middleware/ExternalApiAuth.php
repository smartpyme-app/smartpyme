<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExternalApiAuth
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
        // Obtener el API key del header Authorization
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('API key requerido en header Authorization Bearer');
        }

        $apiKey = trim(substr($authHeader, 7)); // Remover "Bearer " y espacios
        
        if (empty($apiKey)) {
            return $this->unauthorizedResponse('API key no puede estar vacío');
        }

        // Verificar que no sea un JWT (los JWT tienen 3 partes separadas por puntos)
        if (substr_count($apiKey, '.') >= 2) {
            return $this->unauthorizedResponse('Formato de API key inválido');
        }

        // Buscar la empresa por woocommerce_api_key con cache
        $cacheKey = "external_api_empresa_{$apiKey}";
        $empresa = Cache::remember($cacheKey, 3600, function () use ($apiKey) {
            return Empresa::where('woocommerce_api_key', $apiKey)
                          ->where('activo', 1)
                          ->first();
        });

        if (!$empresa) {
            // Log del intento de acceso no autorizado
            Log::warning('Intento de acceso con API key inválido', [
                'api_key' => substr($apiKey, 0, 8) . '...',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->getPathInfo()
            ]);
            
            return $this->unauthorizedResponse('API key inválido o empresa inactiva');
        }

        // Verificar rate limiting
        if ($this->isRateLimited($apiKey, $request)) {
            return $this->rateLimitResponse();
        }

        // Agregar la empresa al request para uso posterior
        $request->attributes->set('empresa', $empresa);
        
        // Log de acceso exitoso
        Log::info('Acceso API externo exitoso', [
            'empresa_id' => $empresa->id,
            'empresa_nombre' => $empresa->nombre,
            'endpoint' => $request->getPathInfo(),
            'ip' => $request->ip()
        ]);

        return $next($request);
    }

    /**
     * Verificar rate limiting con ventana fija por hora
     */
    private function isRateLimited($apiKey, Request $request): bool
    {
        // Crear ventana fija basada en la hora actual
        $currentHour = now()->format('Y-m-d-H'); // Ej: "2024-10-21-14"
        $key = "rate_limit_external_api_{$apiKey}_{$currentHour}";
        
        $maxAttempts = 1000; // 1000 requests por hora
        
        // Si tiene filtros de fecha, permitir más requests
        if ($request->has(['fecha_inicio', 'fecha_fin'])) {
            $maxAttempts = 2000; // 2000 requests por hora con filtros
        }

        // Obtener intentos actuales en esta ventana horaria
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return true;
        }

        // Incrementar contador y establecer expiración al final de la hora actual
        $endOfHour = now()->endOfHour()->addSecond(); // Final de la hora + 1 segundo
        $secondsUntilEndOfHour = now()->diffInSeconds($endOfHour);
        
        Cache::put($key, $attempts + 1, $secondsUntilEndOfHour);
        
        return false;
    }

    /**
     * Respuesta de no autorizado
     */
    private function unauthorizedResponse($message = 'No autorizado')
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'code' => 401
        ], 401);
    }

    /**
     * Respuesta de rate limit excedido
     */
    private function rateLimitResponse()
    {
        return response()->json([
            'success' => false,
            'error' => 'Rate limit excedido. Máximo 1000 requests por hora (2000 con filtros de fecha)',
            'code' => 429,
            'details' => [
                'standard_limit' => 1000,
                'with_date_filters_limit' => 2000,
                'window' => '60 minutos',
                'reset_info' => 'El límite se resetea cada hora'
            ]
        ], 429);
    }
}
