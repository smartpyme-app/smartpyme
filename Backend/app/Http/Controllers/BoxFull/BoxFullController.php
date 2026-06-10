<?php

namespace App\Http\Controllers\BoxFull;

use App\Http\Controllers\Controller;
use App\Services\BoxFull\BoxFullService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoxFullController extends Controller
{
    /**
     * @var BoxFullService
     */
    protected $boxfulService;

    /**
     * BoxFullController constructor.
     *
     * @param BoxFullService $boxfulService
     */
    public function __construct(BoxFullService $boxfulService)
    {
        $this->boxfulService = $boxfulService;
    }

    /**
     * Test the Boxful API connection and authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(Request $request)
    {
        try {
            // 1. Attempt to retrieve access token (will fetch from cache or authenticate)
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

            // Obfuscated token for safety
            $obfuscatedToken = substr($token, 0, 15) . '...' . substr($token, -15);

            // 2. Attempt to query Boxful's /auth/user-info endpoint to verify token validity
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
