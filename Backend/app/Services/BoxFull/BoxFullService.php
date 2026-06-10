<?php

namespace App\Services\BoxFull;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BoxFullService
{
    /**
     * Modelo de la empresa activa.
     */
    protected $empresa;

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
     * Constructor de BoxFullService.
     */
    public function __construct(?Empresa $empresa = null)
    {
        // Ignorar modelos no guardados instanciados automáticamente por el contenedor DI de Laravel
        if ($empresa && $empresa->exists) {
            $this->empresa = $empresa;
        } else {
            $this->empresa = auth()->check() ? auth()->user()->empresa : null;
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
            $this->updateCacheKey();
        }

        $empresa = $this->empresa;
        if (!$empresa) {
            Log::error('Autenticación de API Boxful fallida: No se ha establecido el contexto de la empresa.');
            throw new \Exception('No se ha definido una empresa para la integración con Boxful.');
        }

        // 1. Intentar obtener el token desde la caché (Memcached/Redis/etc.)
        $token = Cache::get($this->cacheKey);
        if ($token) {
            return $token;
        }

        // 2. Si no está en caché, intentar obtenerlo de la base de datos y verificar si sigue vigente (con buffer de 5 minutos)
        if ($empresa->boxful_access_token && 
            $empresa->boxful_token_expires_at && 
            $empresa->boxful_token_expires_at->gt(now()->addMinutes(5))) {
            
            // Repoblar la caché para futuras peticiones rápidas
            $secondsRemaining = max(0, $empresa->boxful_token_expires_at->diffInSeconds(now()));
            if ($secondsRemaining > 0) {
                Cache::put($this->cacheKey, $empresa->boxful_access_token, $secondsRemaining);
            }
            
            return $empresa->boxful_access_token;
        }

        // El token no está en BD o expiró, realizar petición de autenticación
        $email = $empresa->boxful_email;
        $password = $empresa->boxful_password;

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
                $empresa->boxful_status = 'error';
                $empresa->save();

                throw new \Exception('Error de autenticación con Boxful: ' . ($response->json('message') ?? 'Error desconocido'));
            }

            $accessToken = $response->json('accessToken');

            if (empty($accessToken)) {
                Log::error('La respuesta de API Boxful no contiene un accessToken', ['response' => $response->json()]);

                // Actualizar estado en la base de datos a error
                $empresa->boxful_status = 'error';
                $empresa->save();

                throw new \Exception('Respuesta de autenticación inválida desde Boxful.');
            }

            // Guardar token y expiración en la base de datos (válido por 23 horas)
            $empresa->boxful_access_token = $accessToken;
            $empresa->boxful_token_expires_at = now()->addHours(23);
            $empresa->boxful_status = 'connected';

            // Obtener y guardar el clientId desde /auth/user-info
            $this->fetchAndSaveClientId($accessToken, $empresa);

            $empresa->save();

            // Guardar en caché por 23 horas para reducir el tráfico a la base de datos
            Cache::put($this->cacheKey, $accessToken, now()->addHours(23));

            return $accessToken;

        } catch (\Exception $e) {
            Log::error('Excepción al autenticar con Boxful', ['error' => $e->getMessage()]);
            
            // Actualizar estado en la base de datos a error
            $empresa->boxful_status = 'error';
            $empresa->save();

            throw $e;
        }
    }

    /**
     * Obtener el clientId desde /auth/user-info y guardarlo en la base de datos.
     */
    protected function fetchAndSaveClientId(string $accessToken, Empresa $empresa): void
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/auth/user-info';

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $clientId = $response->json('clientId') ?? $response->json('id') ?? $response->json('data.clientId');
                if ($clientId) {
                    $empresa->boxful_client_id = $clientId;
                    Log::info('clientId de Boxful guardado correctamente', [
                        'company_id' => $empresa->id,
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
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param bool $isRetry
     * @return \Illuminate\Http\Client\Response
     * @throws \Exception
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
                $this->empresa->boxful_access_token = null;
                $this->empresa->boxful_token_expires_at = null;
                $this->empresa->boxful_status = 'disconnected';
                $this->empresa->save();
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
}
