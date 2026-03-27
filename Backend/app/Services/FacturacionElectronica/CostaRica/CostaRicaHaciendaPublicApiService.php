<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy con caché hacia la API pública de Hacienda CR (api.hacienda.go.cr).
 *
 * @see https://api.hacienda.go.cr/docs/
 */
final class CostaRicaHaciendaPublicApiService
{
    /**
     * CABYS: por código 13 dígitos o búsqueda por texto q (mín. 3 caracteres).
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    public function cabys(?string $codigo, ?string $q, int $top): array
    {
        $base = $this->baseUrl();
        $top = max(1, min(50, $top));

        if ($codigo !== null && $codigo !== '') {
            $codigo = preg_replace('/\D/', '', $codigo);
            $key = 'fe_cr:hacienda:cabys:cod:'.$codigo;
            $ttl = (int) config('services.hacienda_cr.cache.cabys_codigo_seconds', 21600);

            return $this->rememberSuccessful($key, $ttl, function () use ($base, $codigo) {
                return $this->requestJson($base.'/fe/cabys', ['codigo' => $codigo]);
            });
        }

        $qNorm = $this->normalizeCabysQuery($q);
        $key = 'fe_cr:hacienda:cabys:q:'.md5($qNorm).':'.$top;
        $ttl = (int) config('services.hacienda_cr.cache.cabys_query_seconds', 3600);

        return $this->rememberSuccessful($key, $ttl, function () use ($base, $qNorm, $top) {
            return $this->requestJson($base.'/fe/cabys', ['q' => $qNorm, 'top' => $top]);
        });
    }

    /**
     * Datos de contribuyente y actividades económicas (/fe/ae).
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    public function contribuyente(string $identificacion): array
    {
        $id = preg_replace('/\D/', '', $identificacion);
        $key = 'fe_cr:hacienda:ae:'.$id;
        $ttl = (int) config('services.hacienda_cr.cache.contribuyente_seconds', 43200);

        return $this->rememberSuccessful($key, $ttl, function () use ($id) {
            return $this->requestJson($this->baseUrl().'/fe/ae', ['identificacion' => $id]);
        });
    }

    /**
     * Información de exoneración (/fe/ex).
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    public function exoneracion(string $autorizacion): array
    {
        $auth = strtolower(trim($autorizacion));
        $key = 'fe_cr:hacienda:ex:'.$auth;
        $ttl = (int) config('services.hacienda_cr.cache.exoneracion_seconds', 7200);

        return $this->rememberSuccessful($key, $ttl, function () use ($auth) {
            return $this->requestJson($this->baseUrl().'/fe/ex', ['autorizacion' => $auth]);
        });
    }

    /**
     * Tipo de cambio del dólar según Hacienda (/indicadores/tc/dolar).
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    public function tipoCambioDolar(): array
    {
        $key = 'fe_cr:hacienda:tc:dolar';
        $ttl = (int) config('services.hacienda_cr.cache.tipo_cambio_seconds', 1800);

        return $this->rememberSuccessful($key, $ttl, function () {
            return $this->requestJson($this->baseUrl().'/indicadores/tc/dolar', []);
        });
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.hacienda_cr.base_url', 'https://api.hacienda.go.cr'), '/');
    }

    private function normalizeCabysQuery(?string $q): string
    {
        $q = trim((string) $q);
        $q = preg_replace('/\s+/', ' ', $q);

        return $q;
    }

    /**
     * @param  callable(): array{ok: bool, status: int, data: mixed}  $fetch
     * @return array{ok: bool, status: int, data: mixed}
     */
    private function rememberSuccessful(string $key, int $ttl, callable $fetch): array
    {
        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['status'], $cached['data'])) {
            return $cached;
        }

        $result = $fetch();

        if (($result['status'] ?? 0) === 200 && ($result['ok'] ?? false)) {
            Cache::put($key, $result, max(60, $ttl));
        }

        return $result;
    }

    /**
     * @return array{ok: bool, status: int, data: mixed}
     */
    private function requestJson(string $url, array $query): array
    {
        try {
            $response = Http::timeout((int) config('services.hacienda_cr.timeout_seconds', 25))
                ->withHeaders($this->haciendaRequestHeaders())
                ->get($url, $query);

            $status = $response->status();
            $bodyRaw = $response->body();
            $contentType = (string) $response->header('Content-Type');

            if ($this->responseLooksLikeHtml($bodyRaw, $contentType)) {
                Log::warning('FE CR Hacienda devolvió HTML (bloqueo WAF o error intermedio)', [
                    'url' => $url,
                    'http_status' => $status,
                    'snippet' => mb_substr($bodyRaw, 0, 400),
                ]);

                return [
                    'ok' => false,
                    'status' => 502,
                    'data' => [
                        'error' => 'El Ministerio de Hacienda no devolvió datos en formato esperado (respuesta bloqueada o página de error). Espere unos minutos, evite muchas búsquedas seguidas o pruebe desde otra red. Si persiste, consulte facturati@hacienda.go.cr o seguridaddigital@hacienda.go.cr.',
                        'code' => 'hacienda_html_response',
                    ],
                ];
            }

            $data = $response->json();
            if ($data === null && $bodyRaw !== '') {
                $decoded = json_decode($bodyRaw, true);
                $data = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            }

            if ($data === null && $bodyRaw !== '') {
                return [
                    'ok' => false,
                    'status' => 502,
                    'data' => [
                        'error' => 'Respuesta de Hacienda no es JSON válido.',
                        'code' => 'hacienda_invalid_json',
                    ],
                ];
            }

            $ok = $response->successful() && is_array($data);

            return [
                'ok' => $ok,
                'status' => $status,
                'data' => $data ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('FE CR Hacienda API request failed', [
                'url' => $url,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 503,
                'data' => [
                    'error' => 'No se pudo contactar la API de Hacienda. Intente más tarde.',
                ],
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function haciendaRequestHeaders(): array
    {
        $ua = (string) config(
            'services.hacienda_cr.user_agent',
            'Mozilla/5.0 (compatible; SmartPyme-FE-CR/1.0)'
        );

        return [
            'Accept' => 'application/json, text/plain;q=0.9, */*;q=0.8',
            'Accept-Language' => 'es-CR,es;q=0.9,en;q=0.7',
            'User-Agent' => $ua,
            'Cache-Control' => 'no-cache',
        ];
    }

    private function responseLooksLikeHtml(string $body, string $contentType): bool
    {
        if ($body === '') {
            return false;
        }

        if (stripos($contentType, 'text/html') !== false) {
            return true;
        }

        $trim = ltrim($body);

        return str_starts_with($trim, '<!DOCTYPE') || str_starts_with(strtolower($trim), '<html');
    }
}
