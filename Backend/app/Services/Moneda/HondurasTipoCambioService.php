<?php

namespace App\Services\Moneda;

use App\Models\PaisConfiguracion;
use App\Support\Admin\MonedaDefaultPorPais;
use Carbon\Carbon;

/**
 * Tipo de cambio USD → HNL (referencia BCH). Cache del día en pais_configuracion.
 * Fallback: rate_manual. La UI puede sobrescribir (permitir_editar) con tasa BAC, etc.
 */
final class HondurasTipoCambioService
{
    public const PAIS = 'HN';

    public function __construct(private readonly BchTipoCambioClient $client) {}

    public function rateForDate(\DateTimeInterface $date): float
    {
        $day = Carbon::instance(\DateTimeImmutable::createFromInterface($date))
            ->timezone('America/Tegucigalpa')
            ->startOfDay();
        $dayStr = $day->toDateString();
        $todayStr = now('America/Tegucigalpa')->toDateString();

        $cfg = $this->monedaConfig();
        $cached = $cfg['rate_del_dia'] ?? null;
        if (is_array($cached)
            && ($cached['date'] ?? null) === $dayStr
            && (float) ($cached['rate'] ?? 0) > 0
        ) {
            return (float) $cached['rate'];
        }

        try {
            $rate = $this->client->fetchReferenciaRate($day);
        } catch (\RuntimeException $e) {
            $manual = (float) ($cfg['rate_manual'] ?? 0);
            if ($manual > 0) {
                return $manual;
            }
            throw $e;
        }

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

        throw new \RuntimeException('No hay tipo de cambio BCH (97) para la fecha '.$dayStr);
    }

    /** @return array<string, mixed> */
    private function monedaConfig(): array
    {
        $row = PaisConfiguracion::query()
            ->pais(self::PAIS)
            ->modulo(PaisConfiguracion::MODULO_MONEDA)
            ->first();

        if ($row && is_array($row->configuracion)) {
            return array_merge(MonedaDefaultPorPais::plantilla(self::PAIS), $row->configuracion);
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

        $cfg = is_array($row->configuracion)
            ? array_merge(MonedaDefaultPorPais::plantilla(self::PAIS), $row->configuracion)
            : MonedaDefaultPorPais::plantilla(self::PAIS);
        $cfg['rate_del_dia'] = [
            'date' => $dayStr,
            'from' => 'USD',
            'to' => 'HNL',
            'rate' => $rate,
            'fetched_at' => now('America/Tegucigalpa')->toIso8601String(),
        ];
        $row->configuracion = $cfg;
        $row->save();
    }
}
