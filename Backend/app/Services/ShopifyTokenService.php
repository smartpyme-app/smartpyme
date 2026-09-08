<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyTokenService
{
    /**
     * Tiempo de vida con el que se solicita el token (una hora menos que el
     * máximo de Shopify, ~24h, para renovar antes de que expire).
     */
    private const TTL_SECONDS = 82800; // 23 horas

    private const CACHE_PREFIX = 'shopify_access_token_empresa_';

    /**
     * Obtiene un token de acceso válido para la empresa.
     *
     * Si ya existe un token vigente (BD o caché) lo devuelve; si venció o no
     * existe, lo genera vía client_credentials y lo persiste en la empresa.
     *
     * @return string|null
     */
    public function getAccessToken(Empresa $empresa): ?string
    {
        if (empty($empresa->shopify_store_url)) {
            Log::error('ShopifyTokenService: empresa sin shopify_store_url.', ['empresa_id' => $empresa->id]);
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $empresa->id;

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if ($this->tokenVigente($empresa)) {
            $this->cacheToken($empresa, $empresa->shopify_access_token);
            return $empresa->shopify_access_token;
        }

        return $this->renovarToken($empresa);
    }

    /**
     * Fuerza una nueva generación del token (ignora el cache y BD).
     *
     * @return string|null
     */
    public function renovarToken(Empresa $empresa): ?string
    {
        $clientId = $empresa->shopify_client_id;
        $clientSecret = $empresa->shopify_consumer_secret ?: $empresa->shopify_client_secret;

        if (empty($clientId) || empty($clientSecret)) {
            Log::error('ShopifyTokenService: faltan client_id/client_secret para renovar token.', [
                'empresa_id' => $empresa->id,
            ]);
            return null;
        }

        $shop = rtrim($empresa->shopify_store_url, '/');

        try {
            $response = Http::asForm()->post($shop . '/admin/oauth/access_token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->failed()) {
                Log::error('ShopifyTokenService: error renovando token.', [
                    'empresa_id' => $empresa->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $token = $data['access_token'] ?? null;

            if (empty($token)) {
                Log::error('ShopifyTokenService: respuesta sin access_token.', [
                    'empresa_id' => $empresa->id,
                    'body' => $data,
                ]);
                return null;
            }

            $expiresIn = (int) ($data['expires_in'] ?? self::TTL_SECONDS);
            $expiresAt = now()->addSeconds(min($expiresIn, self::TTL_SECONDS));

            // Persistir en la empresa (sin disparar observers/eventos innecesarios)
            $empresa->forceFill([
                'shopify_access_token' => $token,
                'shopify_token_expires_at' => $expiresAt,
            ])->saveQuietly();

            $this->cacheToken($empresa, $token);

            Log::info('ShopifyTokenService: token renovado.', [
                'empresa_id' => $empresa->id,
                'expires_at' => $expiresAt->toDateTimeString(),
            ]);

            return $token;
        } catch (\Throwable $e) {
            Log::error('ShopifyTokenService: excepción renovando token: ' . $e->getMessage(), [
                'empresa_id' => $empresa->id,
            ]);
            return null;
        }
    }

    /**
     * Indica si el token guardado en BD aún está vigente.
     */
    private function tokenVigente(Empresa $empresa): bool
    {
        if (empty($empresa->shopify_access_token)) {
            return false;
        }

        if (empty($empresa->shopify_token_expires_at)) {
            return true; // Sin fecha = se asume sin vencimiento (token offline antiguo)
        }

        return now()->lt($empresa->shopify_token_expires_at);
    }

    private function cacheToken(Empresa $empresa, string $token): void
    {
        Cache::put(self::CACHE_PREFIX . $empresa->id, $token, now()->addSeconds(self::TTL_SECONDS));
    }

    public function olvidarCache(Empresa $empresa): void
    {
        Cache::forget(self::CACHE_PREFIX . $empresa->id);
    }
}
