<?php

namespace App\Services\Ventas;

use Carbon\Carbon;

/**
 * Decide si hoy toca generar una venta recurrente y con qué período.
 * El mes (anual) sale de la plantilla; el día puede ser distinto (ej. venta del 10, generar el 5).
 */
class RecurrenciaCalendario
{
    public static function diaEjecucion(?int $diaGeneracion, string $fechaPlantilla): int
    {
        if ($diaGeneracion !== null && $diaGeneracion >= 1 && $diaGeneracion <= 31) {
            return $diaGeneracion;
        }

        return Carbon::parse($fechaPlantilla)->day;
    }

    public static function corresponde(
        string $frecuencia,
        string $fechaPlantilla,
        string $hoy,
        ?int $diaGeneracion = null,
    ): bool {
        if (!in_array($frecuencia, ['mensual', 'anual'], true)) {
            return false;
        }

        $origen = Carbon::parse($fechaPlantilla)->startOfDay();
        $fecha = Carbon::parse($hoy)->startOfDay();

        if ($fecha->lessThanOrEqualTo($origen)) {
            return false;
        }

        if ($fecha->day !== self::diaEjecucion($diaGeneracion, $fechaPlantilla)) {
            return false;
        }

        if ($frecuencia === 'anual' && $fecha->month !== $origen->month) {
            return false;
        }

        return true;
    }

    public static function periodo(string $hoy): string
    {
        return Carbon::parse($hoy)->format('Y-m');
    }
}
