<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tipo de cambio USD → CRC para comprobantes en dólares (venta / Hacienda CR).
 * Prioridad: custom_empresa.facturacion_fe.tipo_cambio_usd_crc, caché API, valor por defecto.
 */
final class CostaRicaTipoCambioService
{
    private const CACHE_KEY = 'fe_cr_tipo_cambio_usd_crc';

    private const CACHE_TTL_SECONDS = 3600;

    private const FALLBACK_CRC_PER_USD = 520.0;

    /**
     * CRC por 1 USD (para campo exchange_rate cuando currency_code es USD).
     */
    public function crcPorUsdVenta(Empresa $empresa): float
    {
        $manual = $empresa->getCustomConfigValue('facturacion_fe', 'tipo_cambio_usd_crc', null);
        if ($manual !== null && $manual !== '' && is_numeric($manual)) {
            $v = (float) $manual;

            return $v > 0 ? $v : self::FALLBACK_CRC_PER_USD;
        }

        return (float) Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->fetchCrcPerUsdFromApis() ?? self::FALLBACK_CRC_PER_USD;
        });
    }

    private function fetchCrcPerUsdFromApis(): ?float
    {
        $urls = [
            'https://api.exchangerate.host/latest?base=USD&symbols=CRC',
            'https://open.er-api.com/v6/latest/USD',
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(12)->acceptJson()->get($url);
                if (! $response->successful()) {
                    continue;
                }
                $data = $response->json();
                $rate = $data['rates']['CRC'] ?? null;
                if (is_numeric($rate) && (float) $rate > 0) {
                    return (float) $rate;
                }
            } catch (\Throwable $e) {
                Log::debug('FE CR tipo cambio API falló', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }
}
