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
     * Resuelve el contexto de la empresa y la integración si no están establecidos pero el usuario está autenticado.
     */
    protected function resolveEmpresaIfNeeded(): void
    {
        if (!$this->empresa && auth()->check()) {
            $this->empresa = auth()->user()->empresa;
            if ($this->empresa) {
                $this->integracion = Integracion::where('id_empresa', $this->empresa->id)
                    ->where('proveedor', 'boxful')
                    ->where('estado', 'connected')
                    ->first();
                $this->updateCacheKey();
            }
        }
    }

    /**
     * Obtener o refrescar el token de acceso desde las credenciales de la empresa.
     */
    public function getAccessToken(): string
    {
        // Resolver el contexto de la empresa dinámicamente si aún no se ha establecido (ej. si se instanció antes de ejecutar el middleware de auth)
        $this->resolveEmpresaIfNeeded();

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

        // 3. Si el token de acceso expiró o no está disponible, intentar renovarlo con el refresh token (si existe y no está expirado)
        $refreshToken = $integracion->refresh_token;
        $refreshTokenExpiresAt = $integracion->getConfig('refresh_token_expires_at');
        $isRefreshTokenExpired = false;
        if ($refreshTokenExpiresAt) {
            $isRefreshTokenExpired = now()->timestamp >= $refreshTokenExpiresAt;
        }

        if (!empty($refreshToken) && !$isRefreshTokenExpired) {
            try {
                $urlRefresh = rtrim($this->baseUrl, '/') . '/auth/v2/refresh';
                Log::info('Intentando refrescar token con la API de Boxful (v2)', [
                    'url' => $urlRefresh,
                    'company_id' => $empresa->id
                ]);

                $responseRefresh = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($urlRefresh, [
                    'refreshToken' => $refreshToken,
                ]);

                if ($responseRefresh->successful()) {
                    $accessToken = $responseRefresh->json('accessToken');
                    $newRefreshToken = $responseRefresh->json('refreshToken');
                    $accessTokenExpiresAt = $responseRefresh->json('accessTokenExpiresAt');
                    $refreshTokenExpiresAt = $responseRefresh->json('refreshTokenExpiresAt');

                    if (!empty($accessToken)) {
                        $integracion->access_token = $accessToken;
                        if (!empty($newRefreshToken)) {
                            $integracion->refresh_token = $newRefreshToken;
                        }
                        
                        $expiresAt = $accessTokenExpiresAt ? \Carbon\Carbon::createFromTimestamp($accessTokenExpiresAt) : now()->addHours(23);
                        $integracion->token_expires_at = $expiresAt;
                        $integracion->estado = 'connected';

                        if ($refreshTokenExpiresAt) {
                            $integracion->setConfig(['refresh_token_expires_at' => $refreshTokenExpiresAt]);
                        }

                        $this->fetchAndSaveClientId($accessToken, $integracion);
                        $integracion->save();
                        $this->integracion = $integracion;

                        $this->updateOrCreateChannel($empresa);

                        $secondsRemaining = max(0, $expiresAt->diffInSeconds(now()));
                        if ($secondsRemaining > 0) {
                            Cache::put($this->cacheKey, $accessToken, $secondsRemaining);
                        }

                        Log::info('Token de Boxful refrescado exitosamente.', ['company_id' => $empresa->id]);
                        return $accessToken;
                    }
                } else {
                    Log::warning('Intento de refrescar token de Boxful falló. Procediendo con autenticación por credenciales.', [
                        'status' => $responseRefresh->status(),
                        'response' => $responseRefresh->json(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Excepción al intentar refrescar token de Boxful. Procediendo con autenticación por credenciales.', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 4. Fallback: El token no está en BD/expiró y la renovación falló o no estaba disponible, realizar petición de autenticación completa
        $email = $integracion->getCredential('email');
        $password = $integracion->getCredential('password');

        if (empty($email) || empty($password)) {
            Log::error('Autenticación de API Boxful fallida: Credenciales no configuradas en BD para la empresa ID: ' . $empresa->id);
            throw new \Exception('Las credenciales de API Boxful no están configuradas para la empresa: ' . $empresa->nombre);
        }

        $url = rtrim($this->baseUrl, '/') . '/auth/v2/client';
        $payload = [
            'email' => $email,
            'password' => $password,
            '_params' => [
                'password' => [
                    'description' => 'Correo',
                    'required' => true,
                ],
                'email' => [
                    'description' => 'Contraseña',
                    'required' => true,
                ],
            ],
        ];

        Log::info('Autenticando con la API de Boxful (v2)', ['url' => $url, 'company_id' => $empresa->id]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::error('Inicio de sesión fallido en API Boxful (v2)', [
                    'company_id' => $empresa->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                // Actualizar estado en la base de datos a error
                $integracion->markAsError('Error de autenticación con Boxful: ' . ($response->json('message') ?? 'Error desconocido'));
                throw new \Exception('Error de autenticación con Boxful: ' . ($response->json('message') ?? 'Error desconocido'));
            }

            $accessToken = $response->json('accessToken');
            $refreshToken = $response->json('refreshToken');
            $accessTokenExpiresAt = $response->json('accessTokenExpiresAt');
            $refreshTokenExpiresAt = $response->json('refreshTokenExpiresAt');

            if (empty($accessToken)) {
                Log::error('La respuesta de API Boxful (v2) no contiene un accessToken', ['response' => $response->json()]);

                // Actualizar estado en la base de datos a error
                $integracion->markAsError('Respuesta de autenticación inválida desde Boxful.');
                throw new \Exception('Respuesta de autenticación inválida desde Boxful.');
            }

            // Guardar token, refresh token y expiración
            $integracion->access_token = $accessToken;
            $integracion->refresh_token = $refreshToken;
            
            // Establecer expiración del token de acceso
            $expiresAt = $accessTokenExpiresAt ? \Carbon\Carbon::createFromTimestamp($accessTokenExpiresAt) : now()->addHours(23);
            $integracion->token_expires_at = $expiresAt;
            $integracion->estado = 'connected';

            // Guardar expiración del refresh token en la configuración
            if ($refreshTokenExpiresAt) {
                $integracion->setConfig(['refresh_token_expires_at' => $refreshTokenExpiresAt]);
            }

            // Obtener y guardar el clientId
            $this->fetchAndSaveClientId($accessToken, $integracion);

            $integracion->save();
            $this->integracion = $integracion;

            // Crear o actualizar el canal de ventas para Boxful
            $this->updateOrCreateChannel($empresa);

            // Guardar en caché por el tiempo restante para reducir el tráfico a la base de datos
            $secondsRemaining = max(0, $expiresAt->diffInSeconds(now()));
            if ($secondsRemaining > 0) {
                Cache::put($this->cacheKey, $accessToken, $secondsRemaining);
            }

            return $accessToken;

        } catch (\Exception $e) {
            Log::error('Excepción al autenticar con Boxful', ['error' => $e->getMessage()]);
            
            // Actualizar estado en la base de datos a error
            $integracion->markAsError($e->getMessage());
            throw $e;
        }
    }

    protected function updateOrCreateChannel($empresa)
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

        //retry up to 3 times with 150ms delay on connection issues or 5xx server errors
        $request = Http::retry(3, 150)
            ->withToken($token)
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
        $this->resolveEmpresaIfNeeded();
        return $this->empresa;
    }

    /**
     * Obtener el modelo de integración activo.
     *
     * @return Integracion|null
     */
    public function getIntegracion(): ?Integracion
    {
        $this->resolveEmpresaIfNeeded();
        if (!$this->integracion && $this->empresa) {
            $this->integracion = Integracion::where('id_empresa', $this->empresa->id)
                ->where('proveedor', 'boxful')
                ->where('estado', 'connected')
                ->first();
        }
        return $this->integracion;
    }

    /**
     * Registra una nueva dirección en la API de Boxful.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function createAddress(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('addresses', $data);
    }

    /**
     * Obtiene los detalles de un envío específico por su ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getShipment(string $id): \Illuminate\Http\Client\Response
    {
        return $this->get("shipment/{$id}");
    }

    /**
     * Obtiene los detalles de una dirección específica por su ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getAddress(string $id): \Illuminate\Http\Client\Response
    {
        return $this->get("addresses/{$id}");
    }

    /**
     * Elimina una dirección específica por su ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Client\Response
     */
    public function deleteAddress(string $id): \Illuminate\Http\Client\Response
    {
        return $this->delete("addresses/{$id}");
    }

    /**
     * Obtiene la lista de paqueterías (couriers) disponibles en Shiphero.
     *
     * @param array $query
     * @return \Illuminate\Http\Client\Response
     */
    public function getShipheroCouriers(array $query = []): \Illuminate\Http\Client\Response
    {
        return $this->get('courier/shiphero', $query);
    }

    /**
     * Obtiene cotización de paqueterías (quoter) basadas en ciudad de origen y destino.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function getQuote(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('quoter', $data);
    }

    /**
     * Crea una orden/envío en Shiphero.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function createShipheroOrder(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('shiphero/orders', $data);
    }

    /**
     * Busca órdenes existentes en Shiphero.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function searchShipheroOrders(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('shiphero/orders/search', $data);
    }

    /**
     * Obtiene la lista de productos de Shiphero.
     *
     * @param array $query
     * @return \Illuminate\Http\Client\Response
     */
    public function getShipheroProducts(array $query = []): \Illuminate\Http\Client\Response
    {
        return $this->get('shiphero/products', $query);
    }

    /**
     * Obtiene los lockers disponibles.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function getAvailableLockers(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('locker/available', $data);
    }

    /**
     * Obtiene la información de rastreo de un envío por su ID.
     *
     * @param string $id
     * @return \Illuminate\Http\Client\Response
     */
    public function getTracking(string $id): \Illuminate\Http\Client\Response
    {
        return $this->get("tracking/{$id}");
    }

    /**
     * Registra un webhook de cliente en Boxful.
     *
     * @param array $data
     * @return \Illuminate\Http\Client\Response
     */
    public function registerClientWebhook(array $data): \Illuminate\Http\Client\Response
    {
        return $this->post('client-webhook', $data);
    }

    /**
     * Sincroniza las direcciones de origen locales con las registradas en Boxful.
     * Si una dirección local o la dirección configurada de la empresa no existe en Boxful,
     * se elimina localmente y se desvincula.
     *
     * @return array Resumen de la sincronización
     */
    public function syncOriginAddresses(): array
    {
        $resultados = [
            'sincronizados' => 0,
            'eliminados_localmente' => 0,
            'desvinculados_config' => false,
            'errores' => null
        ];

        $this->resolveEmpresaIfNeeded();

        //check company context. If empty, sync cannot proceed
        if (!$this->empresa) {
            return $resultados;
        }

        try {
            $response = $this->get('addresses');
            if (!$response->successful()) {
                $resultados['errores'] = 'La llamada a la API de Boxful falló con estado: ' . $response->status();
                return $resultados;
            }

            $addresses = $response->json();
            $addressList = isset($addresses['addresses']) ? $addresses['addresses'] : (isset($addresses['data']) ? $addresses['data'] : $addresses);

            if (!is_array($addressList)) {
                $resultados['errores'] = 'La respuesta de Boxful no tiene un formato de lista válido.';
                return $resultados;
            }

            // Mapear los IDs de dirección válidos en Boxful e importar las que falten localmente
            $validBoxfulIds = [];
            foreach ($addressList as $addr) {
                $addrId = $addr['id'] ?? $addr['addressId'] ?? null;
                if ($addrId) {
                    $validBoxfulIds[] = (string) $addrId;

                    $localAddr = \App\Models\Admin\DireccionOrigen::where('id_empresa', $this->empresa->id)
                        ->where('boxful_address_id', $addrId)
                        ->first();

                    if (!$localAddr) {
                        Log::info("Importando dirección de origen faltante desde Boxful. ID: {$addrId}", [
                            'empresa_id' => $this->empresa->id
                        ]);

                        \App\Models\Admin\DireccionOrigen::create([
                            'id_empresa' => $this->empresa->id,
                            'alias' => $addr['alias'] ?? 'Dirección Boxful',
                            'direccion' => $addr['address'] ?? $addr['direccion'] ?? 'Sin dirección',
                            'referencia' => $addr['referencePoint'] ?? $addr['referencia'] ?? 'Sin referencia',
                            'telefono' => $addr['addressPhone'] ?? $addr['telefono'] ?? '70000000',
                            'codigo_area' => $addr['addressAreaCode'] ?? $addr['codigo_area'] ?? '503',
                            'latitud' => (float) ($addr['latitude'] ?? $addr['latitud'] ?? 0.0),
                            'longitud' => (float) ($addr['longitude'] ?? $addr['longitud'] ?? 0.0),
                            'boxful_state_id' => $addr['stateId'] ?? $addr['boxful_state_id'] ?? '0',
                            'boxful_city_id' => $addr['cityId'] ?? $addr['boxful_city_id'] ?? '0',
                            'boxful_address_id' => $addrId,
                            'es_predeterminada' => false
                        ]);
                    }
                }
            }

            // 1. Sincronizar direcciones locales de origen
            $localAddresses = \App\Models\Admin\DireccionOrigen::where('id_empresa', $this->empresa->id)->get();
            foreach ($localAddresses as $localAddr) {
                if ($localAddr->boxful_address_id) {
                    if (in_array((string)$localAddr->boxful_address_id, $validBoxfulIds)) {
                        $resultados['sincronizados']++;
                    } else {
                        //delete local origin address if it no longer exists on Boxful (soft limit: cascading on boxful_shipments is nullOnDelete)
                        Log::warning("Desincronización de dirección origen local detectada. Boxful ID {$localAddr->boxful_address_id} no existe en Boxful. Eliminando localmente.", [
                            'empresa_id' => $this->empresa->id,
                            'alias' => $localAddr->alias
                        ]);
                        $localAddr->delete();
                        $resultados['eliminados_localmente']++;
                    }
                }
            }

            // 2. Sincronizar dirección de origen configurada en la integración
            $integracion = $this->getIntegracion();
            if ($integracion) {
                $configuredOriginId = $integracion->getConfig('direccion_origen_id');
                if ($configuredOriginId && !in_array((string)$configuredOriginId, $validBoxfulIds)) {
                    Log::warning("Desincronización de dirección origen configurada detectada. ID {$configuredOriginId} no existe en Boxful. Desvinculando de la configuración de la integración.", [
                        'empresa_id' => $this->empresa->id
                    ]);

                    $config = $integracion->config ?? [];
                    unset($config['direccion_origen_id']);
                    $integracion->config = $config;
                    $integracion->save();

                    $resultados['desvinculados_config'] = true;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error en syncOriginAddresses: ' . $e->getMessage());
            $resultados['errores'] = $e->getMessage();
        }

        return $resultados;
    }
}
