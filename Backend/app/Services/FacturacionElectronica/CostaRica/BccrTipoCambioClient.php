<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del Web Service de Indicadores Económicos del BCCR.
 *
 * Indicador 318 = tipo de cambio de VENTA (referencia para comprobantes FE en USD).
 *
 * @see https://gee.bccr.fi.cr/Indicadores/Suscripciones/WS/wsindicadoreseconomicos.asmx
 */
class BccrTipoCambioClient
{
    private const SOAP_ACTION = 'http://ws.sdde.bccr.fi.cr/ObtenerIndicadoresEconomicos';

    public function fetchVentaRate(\DateTimeInterface $date): ?float
    {
        $email = (string) config('services.bccr.email');
        $token = (string) config('services.bccr.token');

        if ($email === '' || $token === '') {
            Log::warning('BCCR: faltan credenciales (BCCR_WS_EMAIL/BCCR_WS_TOKEN); no se puede consultar tipo de cambio 318.');

            return null;
        }

        $fecha = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date);
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
