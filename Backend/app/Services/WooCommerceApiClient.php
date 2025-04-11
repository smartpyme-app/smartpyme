<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceApiClient
{
    protected $baseUrl;
    protected $consumerKey;
    protected $consumerSecret;
    protected $isHttps;

    /**
     * Constructor del cliente API de WooCommerce
     */
    public function __construct($storeUrl, $consumerKey, $consumerSecret)
    {
        $this->baseUrl = rtrim($storeUrl, '/') . '/wp-json/wc/v3/';
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->isHttps = strpos($storeUrl, 'https://') === 0;
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
            $url = $this->baseUrl . ltrim($endpoint, '/');
            Log::info("Enviando petición a WooCommerce API (OAuth)", [
                'method' => $method,
                'url' => $url,
                'params' => $params
            ]);

            // Crear parámetros OAuth
            $oauth = [
                'oauth_consumer_key' => $this->consumerKey,
                'oauth_nonce' => md5(uniqid(mt_rand(), true)),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_version' => '1.0'
            ];

            // Combinar con parámetros adicionales
            $oauthParams = array_merge($oauth, $params);

            // Generar firma
            $baseString = $this->generateBaseString($method, $url, $oauthParams);
            $signature = $this->generateOauthSignature($baseString);
            $oauthParams['oauth_signature'] = $signature;

            // Crear encabezado de autorización
            $authHeader = $this->generateAuthorizationHeader($oauthParams);

            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $authHeader
            ]);

            if ($method === 'GET' || $method === 'DELETE') {
                $response = $request->$method($url, $params);
            } else {
                $response = $request->$method($url, $data);
            }


            if ($response->failed()) {
                Log::error("Error en petición a WooCommerce API", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new \Exception('Error en petición a WooCommerce API: ' . $response->status() . ' - ' . $response->body());
            } else {
                $jsonData = json_decode($response->body(), true);
                $data = [
                    'status' => 'success',
                    'body' => $jsonData
                ];

                return $data;
            }
        } catch (\Exception $e) {
            Log::error("Excepción en petición a WooCommerce API: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Genera la cadena base para la firma OAuth
     */
    private function generateBaseString($method, $url, $params)
    {
        ksort($params);
        $baseString = strtoupper($method) . '&';
        $baseString .= rawurlencode($url) . '&';
        $baseString .= rawurlencode(http_build_query($params));
        return $baseString;
    }


    private function generateOauthSignature($baseString)
    {
        $key = rawurlencode($this->consumerSecret) . '&';
        return base64_encode(hash_hmac('sha1', $baseString, $key, true));
    }


    private function generateAuthorizationHeader($params)
    {
        $authorization = 'OAuth ';
        $first = true;

        foreach ($params as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                if (!$first) {
                    $authorization .= ', ';
                }
                $authorization .= rawurlencode($key) . '="' . rawurlencode($value) . '"';
                $first = false;
            }
        }

        return $authorization;
    }
}
