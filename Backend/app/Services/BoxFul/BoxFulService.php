<?php

namespace App\Services\BoxFul;

use App\Models\Admin\Empresa;
use App\Models\Admin\Integracion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BoxFulService
{
    /**
     * Modelo de la empresa activa.
     */
    protected $empresa;

    /**
     * Modelo de la integración activa.
     */
    protected $integracion;

    /**
     * Entorno activo ('production' o 'development').
     */
    protected $env;

    /**
     * URL base activa de la API de Boxful.
     */
    protected $baseUrl;

    /**
     * Llave de caché para diferenciar los tokens de cada empresa.
     */
    protected $cacheKey;

    /**
     * Constructor de BoxFulService.
     */
    public function __construct(?Empresa $empresa = null)
    {
        // Ignorar modelos no guardados instanciados automáticamente por el contenedor DI de Laravel
        if ($empresa && $empresa->exists) {
            $this->empresa = $empresa;
        } else {
            $this->empresa = auth()->check() ? auth()->user()->empresa : null;
        }

        if ($this->empresa) {
            $this->integracion = Integracion::where('id_empresa', $this->empresa->id)
                ->where('proveedor', 'boxful')
                ->where('estado', 'connected')
                ->first();
        }

        $config = config('services.boxful');
        $this->env = strtolower($config['env'] ?? 'development');

        // Normalizar el nombre del entorno y establecer la URL base
        if (in_array($this->env, ['production', 'produccion'])) {
            $this->baseUrl = $config['urls']['production'] ?? 'https://api.goboxful.com';
        } else {
            $this->baseUrl = $config['urls']['development'] ?? 'https://devapi.goboxful.com';
        }

        $this->updateCacheKey();
    }

    /**
     * Establecer el contexto de la empresa dinámicamente.
     */
    public function forEmpresa(Empresa $empresa): self
    {
        $this->empresa = $empresa;
        $this->integracion = Integracion::where('id_empresa', $empresa->id)
            ->where('proveedor', 'boxful')
            ->where('estado', 'connected')
            ->first();
        $this->updateCacheKey();

        return $this;
    }

    /**
     * Actualizar la llave de caché basada en la empresa actual.
     */
    protected function updateCacheKey(): void
    {
        $id = $this->empresa ? $this->empresa->id : 'default';
        $this->cacheKey = 'boxful_access_token_empresa_' . $id;
    }

    /**
     * Obtener o refrescar el token de acceso desde las credenciales de la empresa.
     */
    public function getAccessToken(): string
    {
        // Resolver el contexto de la empresa dinámicamente si aún no se ha establecido (ej. si se instanció antes de ejecutar el middleware de auth)
        if (!$this->empresa && auth()->check()) {
            $this->empresa = auth()->user()->empresa;
            $this->integracion = Integracion::where('id_empresa', $this->empresa->id)
                ->where('proveedor', 'boxful')
                ->where('estado', 'connected')
                ->first();
            $this->updateCacheKey();
        }

        $empresa = $this->empresa;
        if (!$empresa) {
            Log::error('Autenticación de API Boxful fallida: No se ha establecido el contexto de la empresa.');
            throw new \Exception('No se ha definido una empresa para la integración con Boxful.');
        }

        // Si no se encuentra una integración conectada en el constructor, buscar o crear una
        $integracion = $this->integracion;
        if (!$integracion) {
            $integracion = $empresa->obtenerOcrearIntegracion();
        }

        // Autorecuperación: Si el client_id está vacío pero hay un token en caché o BD
        $clientId = $integracion->getCredential('client_id');
        if (empty($clientId)) {
            $existingToken = Cache::get($this->cacheKey) ?? $integracion->access_token;
            if (!empty($existingToken)) {
                $this->fetchAndSaveClientId($existingToken, $integracion);
                $clientId = $integracion->getCredential('client_id');
                if (!empty($clientId)) {
                    $integracion->access_token = $existingToken;
                    if (empty($integracion->token_expires_at)) {
                        $integracion->token_expires_at = now()->addHours(23);
                    }
                    $integracion->estado = 'connected';
                    $integracion->save();
                    $this->integracion = $integracion;
                }
            }
        }

        // 1. Intentar obtener el token desde la caché (Memcached/Redis/etc.)
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        // 2. Si no está en caché, intentar obtenerlo de la base de datos y verificar si sigue vigente (con buffer de 5 minutos)
        if ($integracion->access_token && 
            $integracion->token_expires_at && 
            $integracion->token_expires_at->gt(now()->addMinutes(5))) {
            
            // Repoblar la caché para futuras peticiones rápidas
            $secondsRemaining = max(0, $integracion->token_expires_at->diffInSeconds(now()));
            if ($secondsRemaining > 0) {
                Cache::put($this->cacheKey, $integracion->access_token, $secondsRemaining);
            }
            
            return $integracion->access_token;
        }

        // El token no está en BD o expiró, realizar petición de autenticación
        $email = $integracion->getCredential('email');
        $password = $integracion->getCredential('password');

        if (empty($email) || empty($password)) {
            Log::error('Autenticación de API Boxful fallida: Credenciales no configuradas en BD para la empresa ID: ' . $empresa->id);
            throw new \Exception('Las credenciales de API Boxful no están configuradas para la empresa: ' . $empresa->nombre);
        }

        $url = rtrim($this->baseUrl, '/') . '/auth/client';
        $payload = [
            'email' => $email,
            'password' => $password,
        ];

        Log::info('Autenticando con la API de Boxful', ['url' => $url, 'company_id' => $empresa->id]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::error('Inicio de sesión fallido en API Boxful', [
                    'company_id' => $empresa->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                // Actualizar estado en la base de datos a error
                $integracion->markAsError('Error de autenticación con Boxful: ' . ($response->json('message') ?? 'Error desconocido'));
                throw new \Exception('Error de autenticación con Boxful: ' . ($response->json('message') ?? 'Error desconocido'));
            }

            $accessToken = $response->json('accessToken');

            if (empty($accessToken)) {
                Log::error('La respuesta de API Boxful no contiene un accessToken', ['response' => $response->json()]);

                // Actualizar estado en la base de datos a error
                $integracion->markAsError('Respuesta de autenticación inválida desde Boxful.');
                throw new \Exception('Respuesta de autenticación inválida desde Boxful.');
            }

            // Guardar token y expiración en la base de datos (válido por 23 horas)
            $integracion->access_token = $accessToken;
            $integracion->token_expires_at = now()->addHours(23);
            $integracion->estado = 'connected';

            // Obtener y guardar el clientId
            $this->fetchAndSaveClientId($accessToken, $integracion);

            $integracion->save();
            $this->integracion = $integracion;

            // Crear o actualizar el canal de ventas para Boxful
            $this->updateOrCreateChannel($empresa);

            // Guardar en caché por 23 horas para reducir el tráfico a la base de datos
            Cache::put($this->cacheKey, $accessToken, now()->addHours(23));

            return $accessToken;

        } catch (\Exception $e) {
            Log::error('Excepción al autenticar con Boxful', ['error' => $e->getMessage()]);
            
            // Actualizar estado en la base de datos a error
            $integracion->markAsError($e->getMessage());
            throw $e;
        }
    }

    private function updateOrCreateChannel($empresa)
    {
         \App\Models\Admin\Canal::withoutGlobalScopes()->updateOrCreate(
            [
                'id_empresa' => $empresa->id,
                'nombre' => 'Boxful'
            ],
            [
                'descripcion' => 'Canal de venta para integración con Boxful',
                'enable' => true,
                'cobra_propina' => false,
                'envios' => true
            ]
        );
    }

    /**
     * Obtener el clientId desde /auth/user-info o decodificando el JWT y guardarlo en la base de datos.
     */
    protected function fetchAndSaveClientId(string $accessToken, Integracion $integracion): void
    {
        // 1. Intentar decodificar el JWT primero (rápido, sin peticiones HTTP)
        try {
            $tokenParts = explode('.', $accessToken);
            if (count($tokenParts) >= 2) {
                // El payload del JWT es la segunda parte (índice 1)
                $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
                if ($payloadJson) {
                    $payload = json_decode($payloadJson, true);
                    
                    // Buscar clientId o id en el payload
                    $clientId = $payload['clientId'] ?? 
                                $payload['id'] ?? 
                                $payload['user']['clientId'] ?? 
                                $payload['client']['id'] ?? 
                                ($payload['client']['clientId'] ?? null);
                                
                    if ($clientId) {
                        $integracion->setCredentials(['client_id' => $clientId]);
                        Log::info('clientId de Boxful decodificado del JWT correctamente', [
                            'company_id' => $integracion->id_empresa,
                            'clientId' => $clientId,
                        ]);
                        return; // Éxito rápido, evitamos petición HTTP
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo decodificar el JWT para extraer clientId: ' . $e->getMessage());
        }

        // 2. Fallback: Hacer petición HTTP a /auth/user-info
        try {
            $url = rtrim($this->baseUrl, '/') . '/auth/user-info';

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $clientId = $response->json('response.clientId') ?? $response->json('response.id') ?? $response->json('clientId') ?? $response->json('id') ?? $response->json('data.clientId');
                if ($clientId) {
                    $integracion->setCredentials(['client_id' => $clientId]);
                    Log::info('clientId de Boxful obtenido de /auth/user-info correctamente', [
                        'company_id' => $integracion->id_empresa,
                        'clientId' => $clientId,
                    ]);
                } else {
                    Log::warning('No se encontró clientId en la respuesta de /auth/user-info', [
                        'response' => $response->json(),
                    ]);
                }
            } else {
                Log::warning('Error al consultar /auth/user-info de Boxful', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al obtener clientId desde /auth/user-info de Boxful', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar una petición HTTP a la API de Boxful.
     */
    public function request(string $method, string $endpoint, array $data = [], bool $isRetry = false): \Illuminate\Http\Client\Response
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $method = strtolower($method);

        Log::info("Enviando petición a la API de Boxful", ['method' => $method, 'url' => $url]);

        $request = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]);

        // Enviar petición
        $response = $request->$method($url, $data);

        // Refrescar automáticamente el token si obtenemos un código 401 Unauthorized
        if ($response->status() === 401 && !$isRetry) {
            Log::warning('La petición a la API de Boxful devolvió 401 Unauthorized. Limpiando caché y token de base de datos e intentando de nuevo.', [
                'endpoint' => $endpoint
            ]);

            // Limpiar caché
            Cache::forget($this->cacheKey);

            // Limpiar token en la BD
            if ($this->empresa) {
                $integracion = $this->integracion ?? $this->empresa->obtenerOcrearIntegracion();
                $integracion->access_token = null;
                $integracion->token_expires_at = null;
                $integracion->estado = 'disconnected';
                $integracion->save();
                $this->integracion = null;
            }

            // Reintentar la petición con el nuevo token
            return $this->request($method, $endpoint, $data, true);
        }

        Log::info("Respuesta de la API de Boxful", ['response' => $response->json()]);
        return $response;
    }

    /**
     * Realizar una petición GET.
     */
    public function get(string $endpoint, array $query = []): \Illuminate\Http\Client\Response
    {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * Realizar una petición POST.
     */
    public function post(string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        return $this->request('post', $endpoint, $data);
    }

    /**
     * Realizar una petición PUT.
     */
    public function put(string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        return $this->request('put', $endpoint, $data);
    }

    /**
     * Realizar una petición PATCH.
     */
    public function patch(string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        return $this->request('patch', $endpoint, $data);
    }

    /**
     * Realizar una petición DELETE.
     */
    public function delete(string $endpoint, array $data = []): \Illuminate\Http\Client\Response
    {
        return $this->request('delete', $endpoint, $data);
    }

    /**
     * Obtener el nombre del entorno activo.
     */
    public function getEnv(): string
    {
        return $this->env;
    }

    /**
     * Obtener la URL base activa.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Obtener el modelo de la empresa activa.
     *
     * @return Empresa|null
     */
    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    /**
     * Obtener el modelo de integración activo.
     *
     * @return Integracion|null
     */
    public function getIntegracion(): ?Integracion
    {
        if (!$this->integracion && $this->empresa) {
            $this->integracion = Integracion::where('id_empresa', $this->empresa->id)
                ->where('proveedor', 'boxful')
                ->where('estado', 'connected')
                ->first();
        }
        return $this->integracion;
    }
}
