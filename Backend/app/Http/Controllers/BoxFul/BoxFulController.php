<?php

namespace App\Http\Controllers\BoxFul;

use App\Http\Controllers\Controller;
use App\Services\BoxFul\BoxFulService;
use App\Models\Admin\Integracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BoxFulController extends Controller
{
    protected $boxfulService;

    public function __construct(BoxFulService $boxfulService)
    {
        $this->boxfulService = $boxfulService;
    }

    public function getStatus()
    {
        $integracion = Integracion::boxful();
        return response()->json([
            'connected' => $integracion && $integracion->estado === 'connected'
        ]);
    }

    /**
     * Prueba la conexión de la empresa con Boxful.
     */
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

            //synchronize local origin addresses to auto-heal desynchronization during connection test
            $syncResults = $this->boxfulService->syncOriginAddresses();

            return response()->json([
                'status' => 'success',
                'message' => '¡Se ha conectado exitosamente a Boxful!',
                'diagnostics' => [
                    'env' => $this->boxfulService->getEnv(),
                    'base_url' => $this->boxfulService->getBaseUrl(),
                    'obfuscated_token' => $obfuscatedToken,
                    'user_info' => $response->json(),
                    'sync_origin_addresses' => $syncResults
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

    /**
     * Sincroniza explícitamente las direcciones de origen locales con Boxful.
     */
    public function sincronizarDirecciones(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No autorizado.'
                ], 401);
            }

            $syncResults = $this->boxfulService->syncOriginAddresses();

            return response()->json([
                'status' => 'success',
                'message' => 'Direcciones sincronizadas con Boxful correctamente.',
                'data' => $syncResults
            ], 200);
        } catch (\Exception $e) {
            Log::error('BoxFulController@sincronizarDirecciones exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al sincronizar direcciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra la dirección de origen (bodega) en Boxful y la guarda en la configuración.
     */
    public function configurarOrigen(Request $request)
    {
        try {
            $user = auth()->user();
            $empresa = $user ? $user->empresa : null;

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo verificar el contexto de la empresa.'
                ], 400);
            }

            $request->validate([
                'address' => 'required|string|max:500',
                'referencePoint' => 'nullable|string|max:500',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'stateId' => 'required|string',
                'cityId' => 'required|string',
                'addressPhone' => 'required|string|max:20',
                'addressAreaCode' => 'required|string|max:10',
            ]);

            // Filtrar estrictamente para enviar solo los campos requeridos a Boxful
            $payload = $request->only([
                'address', 'referencePoint', 'latitude', 'longitude', 'stateId', 'cityId', 'addressPhone', 'addressAreaCode'
            ]);

            $response = $this->boxfulService->post('addresses', $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful al configurar dirección de origen', [
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al registrar la dirección de origen en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            $boxfulAddressId = $response->json('id') ?? $response->json('data.id') ?? $response->json('addressId');

            if (empty($boxfulAddressId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La API de Boxful no devolvió un ID de dirección válido para la dirección de origen.'
                ], 500);
            }

            // Guardar en la configuración de la integración
            $integracion = $empresa->obtenerOcrearIntegracion();
            $integracion->setConfig(['direccion_origen_id' => $boxfulAddressId]);
            $integracion->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Dirección de origen configurada exitosamente.',
                'direccion_origen_id' => $boxfulAddressId,
                'data' => $response->json()
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos proporcionados no son válidos para Boxful.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulController@configurarOrigen', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al configurar la dirección de origen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registra el webhook de SmartPyme en la API de Boxful.
     */
    public function registrarWebhook(Request $request)
    {
        try {
            $user = auth()->user();
            $empresa = $user ? $user->empresa : null;

            if (!$empresa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se pudo verificar el contexto de la empresa.'
                ], 400);
            }

            $integracion = $empresa->obtenerOcrearIntegracion();

            // Generar un secret aleatorio de 32 caracteres
            $secret = Str::random(32);
            $integracion->setConfig(['webhook_secret' => $secret]);
            $integracion->save();

            // Construir URL pública para el webhook
            $webhookUrl = url('/api/webhook/boxful/' . $integracion->id_empresa);

            // Llamar a la API de Boxful para registrar el webhook
            $payload = [
                'webhook' => $webhookUrl,
                'accessToken' => $secret,
                '_params' => [
                    'webhook' => [
                        'required' => true,
                        'description' => 'URL a la que Boxful enviará las notificaciones del cliente. Debe aceptar POST requests.'
                    ],
                    'accessToken' => [
                        'required' => false,
                        'description' => 'Token opcional para validar la autenticidad de los requests del webhook.'
                    ]
                ]
            ];

            $response = $this->boxfulService->registerClientWebhook($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful al registrar webhook', [
                    'status' => $status,
                    'response' => $body,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error al registrar el webhook en Boxful.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook registrado correctamente en Boxful.',
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $secret,
                'data' => $response->json()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Excepción en BoxFulController@registrarWebhook', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Proxy a Boxful POST /client-webhook.
     */
    public function storeClientWebhook(Request $request)
    {
        try {
            $request->validate([
                'webhook' => 'required|url',
                'accessToken' => 'nullable|string',
            ]);

            $payload = $request->only(['webhook', 'accessToken']);

            if ($request->has('_params')) {
                $payload['_params'] = $request->input('_params');
            } else {
                $payload['_params'] = [
                    'webhook' => [
                        'required' => true,
                        'description' => 'URL a la que Boxful enviará las notificaciones del cliente. Debe aceptar POST requests.'
                    ],
                    'accessToken' => [
                        'required' => false,
                        'description' => 'Token opcional para validar la autenticidad de los requests del webhook.'
                    ]
                ];
            }

            $response = $this->boxfulService->registerClientWebhook($payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('Error de API Boxful en storeClientWebhook', [
                    'status' => $status,
                    'response' => $body
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => is_array($body) ? ($body['message'] ?? $body['error'] ?? 'Error de Boxful al registrar el webhook.') : 'Error de respuesta de la API de Boxful.',
                    'errors' => is_array($body) ? ($body['errors'] ?? null) : null
                ], $status);
            }

            return response()->json($response->json(), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validación local fallida.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('BoxFulController@storeClientWebhook exception: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error al registrar el webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}
