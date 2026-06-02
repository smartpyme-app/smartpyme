<?php

namespace App\Services;

use App\Models\Admin\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MhGovSvGatewayService
{
    public function baseUrl(Empresa $empresa): string
    {
        return $empresa->fe_ambiente === '01'
            ? 'https://api.dtes.mh.gob.sv'
            : 'https://apitest.dtes.mh.gob.sv';
    }

    public function ensureCredentials(Empresa $empresa): void
    {
        if (empty($empresa->mh_usuario) || empty($empresa->mh_contrasena)) {
            abort(422, 'Configure el usuario y contraseña para conectarse a la API de hacienda');
        }
    }

    /**
     * Autenticación en /seguridad/auth (form urlencoded).
     *
     * @return array{token: string, status: int, body: array|null, raw_body: string}
     */
    public function fetchAuthToken(Empresa $empresa): array
    {
        $this->ensureCredentials($empresa);

        $url = $this->baseUrl($empresa) . '/seguridad/auth';
        $t0 = microtime(true);

        try {
            $response = $this->httpClient()->asForm()->post($url, [
                'user' => str_replace('-', '', (string) $empresa->mh_usuario),
                'pwd' => (string) $empresa->mh_contrasena,
            ]);
        } catch (ConnectionException $e) {
            $this->logAuthFailure($empresa, $url, null, null, $t0, $e->getMessage());

            throw $e;
        }

        $durationMs = (int) round((microtime(true) - $t0) * 1000);
        $status = $response->status();
        $rawBody = $response->body();
        $body = $response->json();

        Log::info('MH seguridad/auth', [
            'empresa_id' => $empresa->id,
            'mh_status' => $status,
            'duration_ms' => $durationMs,
            'response_body' => $body,
        ]);

        $token = data_get($body, 'body.token');
        if (!is_string($token) || $token === '') {
            $this->logAuthFailure($empresa, $url, $status, $rawBody, $t0, 'Token ausente en respuesta');

            return [
                'token' => '',
                'status' => $status,
                'body' => is_array($body) ? $body : null,
                'raw_body' => $rawBody,
            ];
        }

        return [
            'token' => $token,
            'status' => $status,
            'body' => is_array($body) ? $body : null,
            'raw_body' => $rawBody,
        ];
    }

    /**
     * POST JSON a un path bajo el host de MH (recepción, consulta, contingencia, anulación).
     *
     * @return array{status: int, body: mixed, raw_body: string, duration_ms: int}
     */
    public function postJson(Empresa $empresa, string $path, array $payload): array
    {
        $auth = $this->fetchAuthToken($empresa);
        if ($auth['token'] === '') {
            return [
                'status' => $auth['status'] >= 400 ? $auth['status'] : 401,
                'body' => $auth['body'] ?? json_decode($auth['raw_body'], true),
                'raw_body' => $auth['raw_body'],
                'duration_ms' => 0,
            ];
        }

        $base = $this->baseUrl($empresa);
        $path = '/' . ltrim($path, '/');
        $url = $base . $path;

        $t0 = microtime(true);

        try {
            $response = $this->httpClient()
                ->withHeaders([
                    'Authorization' => $auth['token'],
                ])
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            $durationMs = (int) round((microtime(true) - $t0) * 1000);
            Log::error('MH POST connection error', [
                'empresa_id' => $empresa->id,
                'url' => $url,
                'payload' => $payload,
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $durationMs = (int) round((microtime(true) - $t0) * 1000);
        $status = $response->status();
        $rawBody = $response->body();

        $decoded = json_decode($rawBody, true);
        $bodyOut = json_last_error() === JSON_ERROR_NONE ? $decoded : $rawBody;

        Log::info('MH POST', [
            'empresa_id' => $empresa->id,
            'url' => $url,
            'payload' => $payload,
            'mh_status' => $status,
            'duration_ms' => $durationMs,
            'response_body' => $bodyOut,
        ]);

        return [
            'status' => $status,
            'body' => $bodyOut,
            'raw_body' => $rawBody,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Cliente HTTP base (timeouts alineados con emisión DTE).
     */
    protected function httpClient()
    {
        $verify = config('mh.verify_ssl', true);

        return Http::timeout((int) config('mh.timeout_seconds', 120))
            ->withOptions([
                'verify' => $verify,
                'http_errors' => false,
                'connect_timeout' => (int) config('mh.connect_timeout_seconds', 30),
            ]);
    }

    protected function logAuthFailure(
        Empresa $empresa,
        string $url,
        ?int $status,
        ?string $rawBody,
        float $t0,
        string $reason
    ): void {
        Log::warning('MH auth fallida', [
            'empresa_id' => $empresa->id,
            'url' => $url,
            'mh_status' => $status,
            'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            'response_body' => $rawBody,
            'reason' => $reason,
        ]);
    }
}
