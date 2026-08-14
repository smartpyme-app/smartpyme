<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente BCCR indicador 318 (tipo de cambio de VENTA).
 *
 * Preferencia: API REST SDDE (Bearer token del portal sdd.bccr.fi.cr).
 * Fallback: WS SOAP legacy gee.bccr.fi.cr (a menudo 503 desde 2025).
 *
 * @see https://sdd.bccr.fi.cr/es/IndicadoresEconomicos/Inicio/
 * @see https://gee.bccr.fi.cr/indicadoreseconomicos/Documentos/DocumentosMetodologiasNotasTecnicas/Estandar_API_SDDE.pdf
 */
class BccrTipoCambioClient
{
    private const SOAP_ACTION = 'http://ws.sdde.bccr.fi.cr/ObtenerIndicadoresEconomicos';

    public function fetchVentaRate(\DateTimeInterface $date): ?float
    {
        $token = (string) config('services.bccr.token');
        if ($token === '') {
            Log::warning('BCCR: falta BCCR_WS_TOKEN; no se puede consultar tipo de cambio 318.');

            throw new \RuntimeException(
                'Falta BCCR_WS_TOKEN en el servidor. Registro y token: https://sdd.bccr.fi.cr/es/IndicadoresEconomicos/Inicio/'
            );
        }

        $fecha = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date);

        $rate = $this->fetchViaSdde($fecha, $token);
        if ($rate !== null) {
            return $rate;
        }

        $email = (string) config('services.bccr.email');
        if ($email === '') {
            return null;
        }

        $fechaFormato = $fecha->format('d/m/Y');
        $params = [
            'Indicador' => (string) config('services.bccr.indicador_venta', 318),
            'FechaInicio' => $fechaFormato,
            'FechaFinal' => $fechaFormato,
            'Nombre' => (string) config('services.bccr.name', 'SmartPyme'),
            'SubNiveles' => 'N',
            'CorreoElectronico' => $email,
            'Token' => $token,
        ];

        $rate = $this->fetchViaGet($params);
        if ($rate !== null) {
            return $rate;
        }

        return $this->fetchViaSoap($params);
    }

    private function fetchViaSdde(\DateTimeInterface $fecha, string $token): ?float
    {
        $indicador = (string) config('services.bccr.indicador_venta', 318);
        $base = rtrim((string) config('services.bccr.sdde_url'), '/');
        $fechaIso = $fecha->format('Y/m/d');
        $url = "{$base}/indicadoresEconomicos/{$indicador}/series";
        $timeout = (int) config('services.bccr.timeout_seconds', 25);

        try {
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => (string) config('services.bccr.user_agent', 'SmartPyme-BCCR/1'),
                ])
                ->get($url, [
                    'fechaInicio' => $fechaIso,
                    'fechaFin' => $fechaIso,
                    'idioma' => 'es',
                ]);

            if (! $response->successful()) {
                Log::warning('BCCR SDDE: respuesta HTTP no exitosa.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseSddeSeriesResponse($response->json());
        } catch (\Throwable $e) {
            Log::warning('BCCR SDDE: fallo la petición de series.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    public function parseSddeSeriesResponse(?array $json): ?float
    {
        if (! is_array($json)) {
            return null;
        }

        $datos = $json['datos'] ?? null;
        if (! is_array($datos) || $datos === []) {
            return null;
        }

        $series = $datos[0]['series'] ?? null;
        if (! is_array($series) || $series === []) {
            return null;
        }

        $valor = $series[0]['valorDatoPorPeriodo'] ?? null;
        if ($valor === null || $valor === '') {
            return null;
        }

        $rate = (float) $valor;

        return $rate > 0 ? $rate : null;
    }

    private function fetchViaGet(array $params): ?float
    {
        $url = rtrim((string) config('services.bccr.url'), '/').'/ObtenerIndicadoresEconomicos';
        $timeout = (int) config('services.bccr.timeout_seconds', 25);

        try {
            $response = Http::timeout($timeout)->asForm()->get($url, $params);
            if (! $response->successful()) {
                Log::warning('BCCR: respuesta HTTP no exitosa en ObtenerIndicadoresEconomicos (GET).', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseResponse($response->body());
        } catch (\Throwable $e) {
            Log::warning('BCCR: fallo la petición GET a ObtenerIndicadoresEconomicos.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function fetchViaSoap(array $params): ?float
    {
        $url = rtrim((string) config('services.bccr.url'), '/');
        $timeout = (int) config('services.bccr.timeout_seconds', 25);
        $body = $this->buildSoapEnvelope($params);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => self::SOAP_ACTION,
                ])
                ->withBody($body, 'text/xml')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('BCCR: respuesta HTTP no exitosa en ObtenerIndicadoresEconomicos (SOAP).', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $this->parseResponse($response->body());
        } catch (\Throwable $e) {
            Log::warning('BCCR: fallo la petición SOAP a ObtenerIndicadoresEconomicos.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildSoapEnvelope(array $params): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'
            .'<ObtenerIndicadoresEconomicos xmlns="http://ws.sdde.bccr.fi.cr">'
            .'<Indicador>'.$esc($params['Indicador']).'</Indicador>'
            .'<FechaInicio>'.$esc($params['FechaInicio']).'</FechaInicio>'
            .'<FechaFinal>'.$esc($params['FechaFinal']).'</FechaFinal>'
            .'<Nombre>'.$esc($params['Nombre']).'</Nombre>'
            .'<SubNiveles>'.$esc($params['SubNiveles']).'</SubNiveles>'
            .'<CorreoElectronico>'.$esc($params['CorreoElectronico']).'</CorreoElectronico>'
            .'<Token>'.$esc($params['Token']).'</Token>'
            .'</ObtenerIndicadoresEconomicos>'
            .'</soap:Body>'
            .'</soap:Envelope>';
    }

    /**
     * Extrae NUM_VALOR de la respuesta del BCCR sin importar el envoltorio (DataSet/diffgram,
     * XML plano, o SOAP con contenido HTML-escapado dentro de un nodo <string>).
     */
    public function parseResponse(string $body): ?float
    {
        if (trim($body) === '') {
            return null;
        }

        $decoded = html_entity_decode($body, ENT_QUOTES | ENT_XML1, 'UTF-8');

        if (! preg_match('/<NUM_VALOR>\s*(-?[0-9]+(?:\.[0-9]+)?)\s*<\/NUM_VALOR>/i', $decoded, $m)) {
            return null;
        }

        $value = (float) $m[1];

        return $value > 0 ? $value : null;
    }
}
