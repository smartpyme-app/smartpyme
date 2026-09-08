<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyApiClient
{
    protected $shopDomain;
    protected $accessToken;
    protected $apiVersion = '2024-01';
    protected $tokenService;
    protected $empresa;

    /**
     * @param string $shopDomain  URL base de la tienda (https://xxx.myshopify.com)
     * @param string|null $accessToken  Token fijo (compatibilidad). Si no se pasa y se
     *                                   provee una Empresa con client_id/client_secret,
     *                                   el token se resuelve y renueva automáticamente.
     * @param ShopifyTokenService|null $tokenService
     * @param Empresa|null $empresa
     */
    public function __construct($shopDomain, $accessToken = null, ?ShopifyTokenService $tokenService = null, ?Empresa $empresa = null)
    {
        $this->shopDomain = rtrim($shopDomain, '/');
        $this->accessToken = $accessToken;
        $this->tokenService = $tokenService ?: app(ShopifyTokenService::class);
        $this->empresa = $empresa;
    }

    public function get($endpoint, $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post($endpoint, $data)
    {
        return $this->request('POST', $endpoint, [], $data);
    }

    public function put($endpoint, $data)
    {
        return $this->request('PUT', $endpoint, [], $data);
    }

    public function delete($endpoint, $params = [])
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    /**
     * Resuelve el token a usar en el request.
     * Si hay empresa con client credentials, renueva automáticamente al vencer.
     */
    protected function resolveToken(bool $forceRefresh = false): ?string
    {
        if ($forceRefresh && $this->empresa) {
            $this->tokenService->olvidarCache($this->empresa);
            return $this->tokenService->renovarToken($this->empresa);
        }

        if ($this->empresa && !empty($this->empresa->shopify_client_id)) {
            return $this->tokenService->getAccessToken($this->empresa);
        }

        return $this->accessToken;
    }

    protected function request($method, $endpoint, $params = [], $data = [], $retryOnUnauthorized = true)
    {
        $token = $this->resolveToken();

        if (empty($token)) {
            throw new \Exception('No se pudo obtener un access token de Shopify.');
        }

        try {
            $url = "{$this->shopDomain}/admin/api/{$this->apiVersion}/" . ltrim($endpoint, '/');

            Log::info("Enviando petición a Shopify API", [
                'method' => $method,
                'url' => $url,
                'params' => $params
            ]);

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Shopify-Access-Token' => $token
            ]);

            if ($method === 'GET' || $method === 'DELETE') {
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                }
                $response = $request->$method($url);
            } else {
                $response = $request->$method($url, $data);
            }

            if ($response->status() === 401 && $retryOnUnauthorized && $this->empresa && !empty($this->empresa->shopify_client_id)) {
                Log::warning('Shopify devolvió 401. Intentando renovar token automáticamente.', [
                    'empresa_id' => $this->empresa->id,
                ]);
                $newToken = $this->resolveToken(true);
                if ($newToken && $newToken !== $token) {
                    return $this->request($method, $endpoint, $params, $data, false);
                }
            }

            if ($response->failed()) {
                Log::error("Error en petición a Shopify API", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Error en petición a Shopify API: ' . $response->status() . ' - ' . $response->body());
            } else {
                $jsonData = json_decode($response->body(), true);
                return [
                    'status' => 'success',
                    'body' => $jsonData
                ];
            }
        } catch (\Exception $e) {
            Log::error("Excepción en petición a Shopify API: " . $e->getMessage());
            throw $e;
        }
    }
}
