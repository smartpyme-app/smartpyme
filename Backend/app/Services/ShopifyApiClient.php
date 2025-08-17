<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyApiClient
{
    protected $shopDomain;
    protected $accessToken;
    protected $apiVersion = '2024-01';

    /**
     * Constructor del cliente API de Shopify
     */
    public function __construct($shopDomain, $accessToken)
    {
        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
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

    protected function request($method, $endpoint, $params = [], $data = [])
    {
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
                'X-Shopify-Access-Token' => $this->accessToken
            ]);

            if ($method === 'GET' || $method === 'DELETE') {
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                }
                $response = $request->$method($url);
            } else {
                $response = $request->$method($url, $data);
            }

            if ($response->failed()) {
                Log::error("Error en petición a Shopify API", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Error en petición a Shopify API: ' . $response->status() . ' - ' . $response->body());
            } else {
                $jsonData = json_decode($response->body(), true);
                $data = [
                    'status' => 'success',
                    'body' => $jsonData
                ];

                return $data;
            }
        } catch (\Exception $e) {
            Log::error("Excepción en petición a Shopify API: " . $e->getMessage());
            throw $e;
        }
    }
}