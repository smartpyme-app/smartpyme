<?php

namespace App\Services\Moneda;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente BCH indicador 97 (EC-TCR-01 — Tipo de Cambio de Referencia HNL/USD).
 *
 * @see https://bchapi-am.developer.azure-api.net/
 */
class BchTipoCambioClient
{
    public function fetchReferenciaRate(\DateTimeInterface $date): ?float
    {
        $key = trim((string) config('services.bch.api_key'));
        if ($key === '') {
            Log::warning('BCH: falta BCH_API_KEY; no se puede consultar tipo de cambio de referencia.');

            throw new \RuntimeException(
                'Falta BCH_API_KEY en el servidor. Registro: https://bchapi-am.developer.azure-api.net/'
            );
        }

        $fecha = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date);
        $dayStr = $fecha->format('Y-m-d');
        $indicador = (int) config('services.bch.indicador_referencia', 97);
        $base = rtrim((string) config('services.bch.base_url'), '/');
        $url = "{$base}/api/v1/indicadores/{$indicador}/cifras";
        $timeout = (int) config('services.bch.timeout_seconds', 25);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $key,
                    'User-Agent' => (string) config('services.bch.user_agent', 'SmartPyme-BCH/1'),
                ])
                ->get($url, [
                    'fechaInicio' => $dayStr,
                    'fechaFinal' => $dayStr,
                ]);

            if (! $response->successful()) {
                Log::warning('BCH: respuesta HTTP no exitosa al consultar cifras.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseCifrasResponse($response->json(), $dayStr);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('BCH: fallo la petición de cifras.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, mixed>|array<string, mixed>|null  $json
     */
    public function parseCifrasResponse(?array $json, string $dayStr): ?float
    {
        if (! is_array($json) || $json === []) {
            return null;
        }

        // Algunas respuestas envuelven la lista; otras son el array plano.
        $rows = array_is_list($json) ? $json : ($json['data'] ?? $json['cifras'] ?? $json['value'] ?? null);
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $bestBefore = null;
        $bestBeforeDate = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $valor = $row['Valor'] ?? $row['valor'] ?? null;
            if ($valor === null || $valor === '' || (float) $valor <= 0) {
                continue;
            }
            $fechaRaw = (string) ($row['Fecha'] ?? $row['fecha'] ?? '');
            if ($fechaRaw === '') {
                continue;
            }
            try {
                $rowDay = (new \DateTimeImmutable($fechaRaw))->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }

            if ($rowDay === $dayStr) {
                return (float) $valor;
            }
            if ($rowDay <= $dayStr && ($bestBeforeDate === null || $rowDay > $bestBeforeDate)) {
                $bestBeforeDate = $rowDay;
                $bestBefore = (float) $valor;
            }
        }

        return $bestBefore;
    }
}
