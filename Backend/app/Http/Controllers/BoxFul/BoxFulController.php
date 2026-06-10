<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoxFulController extends Controller
{
    protected $boxfulService;

    public function __construct(BoxFulService $boxfulService)
    {
        $this->boxfulService = $boxfulService;
    }

    public function testConnection()
    {
        try {
            // 1. Intentar obtener el token de acceso (se recuperará de la caché o se autenticará)
            $token = $this->boxfulService->getAccessToken();

            if (empty($token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El AccessToken está vacío o es inválido.',
                    'diagnostics' => [
                        'env' => $this->boxfulService->getEnv(),
                        'base_url' => $this->boxfulService->getBaseUrl(),
                    ]
                ], 400);
            }

            // Token ofuscado por seguridad
            $obfuscatedToken = substr($token, 0, 15) . '...' . substr($token, -15);

            // 2. Intentar consultar el endpoint /auth/user-info de Boxful para verificar la validez del token
            $response = $this->boxfulService->get('auth/user-info');

            if ($response->failed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La prueba de conexión falló en la verificación de auth/user-info.',
                    'diagnostics' => [
                        'env' => $this->boxfulService->getEnv(),
                        'base_url' => $this->boxfulService->getBaseUrl(),
                        'obfuscated_token' => $obfuscatedToken,
                        'api_response_status' => $response->status(),
                        'api_response_body' => $response->json(),
                    ]
                ], $response->status());
            }

            
            return response()->json([
                'status' => 'success',
                'message' => '¡Se ha conectado exitosamente a Boxful!',
                'diagnostics' => [
                    'env' => $this->boxfulService->getEnv(),
                    'base_url' => $this->boxfulService->getBaseUrl(),
                    'obfuscated_token' => $obfuscatedToken,
                    'user_info' => $response->json(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Excepción al intentar conectar con Boxful', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió una excepción al intentar conectar con Boxful: ' . $e->getMessage(),
                'diagnostics' => [
                    'env' => $this->boxfulService->getEnv(),
                    'base_url' => $this->boxfulService->getBaseUrl(),
                ]
            ], 500);
        }
    }
}
