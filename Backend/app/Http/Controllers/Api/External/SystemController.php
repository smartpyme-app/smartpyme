<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="System",
 *     description="Endpoints del sistema para monitoreo y estado"
 * )
 */
class SystemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/system/rate-limit",
     *     tags={"System"},
     *     summary="Verificar estado del rate limit",
     *     description="Obtiene información sobre el uso actual del rate limit",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estado del rate limit obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="requests_used", type="integer", example=25),
     *                 @OA\Property(property="max_requests", type="integer", example=500),
     *                 @OA\Property(property="remaining_requests", type="integer", example=475),
     *                 @OA\Property(property="reset_time", type="string", format="datetime", example="2024-01-01T15:00:00Z"),
     *                 @OA\Property(property="window_minutes", type="integer", example=60),
     *                 @OA\Property(property="limits", type="object",
     *                     @OA\Property(property="standard", type="integer", example=500),
     *                     @OA\Property(property="with_date_filters", type="integer", example=1000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerError")
     * )
     */
    public function rateLimitStatus(Request $request)
    {
        try {
            $empresa = $request->attributes->get('empresa');
            $apiKey = $this->getApiKeyFromRequest($request);
            
            // Usar el mismo sistema de ventana fija que el middleware
            $currentHour = now()->format('Y-m-d-H'); // Ej: "2024-10-21-14"
            $key = "rate_limit_external_api_{$apiKey}_{$currentHour}";
            $maxAttempts = 1000; // Límite estándar
            
            // Si tiene filtros de fecha, permitir más requests
            $hasDateFilters = $request->has(['fecha_inicio', 'fecha_fin']);
            if ($hasDateFilters) {
                $maxAttempts = 2000;
            }
            
            $attempts = Cache::get($key, 0);
            $remaining = max(0, $maxAttempts - $attempts);
            
            // Calcular tiempo de reset (final de la hora actual)
            $resetTime = now()->endOfHour()->addSecond();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'requests_used' => $attempts,
                    'max_requests' => $maxAttempts,
                    'remaining_requests' => $remaining,
                    'reset_time' => $resetTime->toISOString(),
                    'window_minutes' => 60,
                    'limits' => [
                        'standard' => 1000,
                        'with_date_filters' => 2000
                    ],
                    'current_limit_type' => $hasDateFilters ? 'with_date_filters' : 'standard',
                    'empresa_id' => $empresa->id,
                    'empresa_nombre' => $empresa->nombre
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en consulta de rate limit status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }
    
    /**
     * Obtener API key del request
     */
    private function getApiKeyFromRequest(Request $request)
    {
        $authHeader = $request->header('Authorization');
        return trim(substr($authHeader, 7)); // Remover "Bearer "
    }
}
