<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\FacturacionElectronica\CostaRica\BccrTipoCambio;
use Carbon\Carbon;

/**
 * Tipo de cambio USD → CRC para comprobantes en dólares (venta / Hacienda CR).
 * Fuente única: BCCR indicador 318 (tipo de cambio de venta), cacheado por día en `bccr_tipos_cambio`.
 * Sin fallback numérico: si el BCCR no responde, se lanza excepción (no se emite con un tipo de cambio inventado).
 */
final class CostaRicaTipoCambioService
{
    public function __construct(private readonly BccrTipoCambioClient $client) {}

    public function rateForDate(\DateTimeInterface $date): float
    {
        $day = Carbon::instance(\DateTimeImmutable::createFromInterface($date))->startOfDay();

        $row = BccrTipoCambio::query()->whereDate('date', $day)->first();
        if ($row) {
            return (float) $row->venta_reference_rate;
        }

        $rate = $this->client->fetchVentaRate($day);
        if ($rate === null || $rate <= 0) {
            throw new \RuntimeException('No hay tipo de cambio BCCR (318) para la fecha '.$day->toDateString());
        }

        BccrTipoCambio::query()->updateOrCreate(
            ['date' => $day->toDateString()],
            ['venta_reference_rate' => $rate, 'fetched_at' => now()]
        );

        return (float) $rate;
    }

    /**
     * CRC por 1 USD (para campo exchange_rate cuando currency_code es USD).
     */
    public function crcPorUsdVenta(Empresa $empresa, ?\DateTimeInterface $date = null): float
    {
        // Ignora tipo_cambio_usd_crc manual y APIs genéricas / fallback 520: fuente única BCCR 318.
        $date ??= now('America/Costa_Rica');

        return $this->rateForDate($date);
    }
}
