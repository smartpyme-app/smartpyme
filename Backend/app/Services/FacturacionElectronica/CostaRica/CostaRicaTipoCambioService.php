<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Support\Admin\MonedaDefaultPorPais;
use Carbon\Carbon;

/**
 * Tipo de cambio USD → CRC para comprobantes en dólares (venta / Hacienda CR).
 * Fuente: BCCR indicador 318; cache del día en pais_configuracion (modulo=moneda).
 * Sin fallback numérico inventado: si no hay rate usable, se lanza excepción.
 */
final class CostaRicaTipoCambioService
{
    public const PAIS = 'CR';

    public function __construct(private readonly BccrTipoCambioClient $client) {}

    public function rateForDate(\DateTimeInterface $date): float
    {
        $day = Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            ->timezone('America/Costa_Rica')
            ->startOfDay();
        $dayStr = $day->toDateString();
        $todayStr = now('America/Costa_Rica')->toDateString();

        $cfg = $this->monedaConfig();
        $cached = $cfg['rate_del_dia'] ?? null;
        if (is_array($cached)
            && ($cached['date'] ?? null) === $dayStr
            && (float) ($cached['rate'] ?? 0) > 0
        ) {
            return (float) $cached['rate'];
        }

        $rate = $this->client->fetchVentaRate($day);
        if ($rate !== null && $rate > 0) {
            if ($dayStr === $todayStr) {
                $this->saveRateDelDia($dayStr, (float) $rate);
            }

            return (float) $rate;
        }

        $manual = (float) ($cfg['rate_manual'] ?? 0);
        if ($manual > 0) {
            return $manual;
        }

        throw new \RuntimeException('No hay tipo de cambio BCCR (318) para la fecha '.$dayStr);
    }

    /**
     * CRC por 1 USD (para campo exchange_rate cuando currency_code es USD).
     */
    public function crcPorUsdVenta(Empresa $empresa, ?\DateTimeInterface $date = null): float
    {
        $date ??= now('America/Costa_Rica');

        return $this->rateForDate($date);
    }

    /** @return array<string, mixed> */
    private function monedaConfig(): array
    {
        $row = PaisConfiguracion::query()
            ->pais(self::PAIS)
            ->modulo(PaisConfiguracion::MODULO_MONEDA)
            ->first();

        if ($row && is_array($row->configuracion)) {
            return $row->configuracion;
        }

        return MonedaDefaultPorPais::plantilla(self::PAIS);
    }

    private function saveRateDelDia(string $dayStr, float $rate): void
    {
        $row = PaisConfiguracion::query()->firstOrCreate(
            [
                'pais' => self::PAIS,
                'modulo' => PaisConfiguracion::MODULO_MONEDA,
            ],
            [
                'configuracion' => MonedaDefaultPorPais::plantilla(self::PAIS),
            ]
        );

        $cfg = is_array($row->configuracion) ? $row->configuracion : MonedaDefaultPorPais::plantilla(self::PAIS);
        $cfg['rate_del_dia'] = [
            'date' => $dayStr,
            'from' => 'USD',
            'to' => 'CRC',
            'rate' => $rate,
            'fetched_at' => now('America/Costa_Rica')->toIso8601String(),
        ];
        $row->configuracion = $cfg;
        $row->save();
    }
}
