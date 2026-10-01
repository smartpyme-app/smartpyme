<?php

namespace App\Services\Ventas;

use Carbon\Carbon;

/**
 * Decide si hoy toca generar una venta recurrente y con qué período.
 * El día y el mes salen de la fecha de la venta plantilla.
 */
class RecurrenciaCalendario
{
    public static function corresponde(string $frecuencia, string $fechaPlantilla, string $hoy): bool
    {
        if (!in_array($frecuencia, ['mensual', 'anual'], true)) {
            return false;
        }

        $origen = Carbon::parse($fechaPlantilla)->startOfDay();
        $fecha = Carbon::parse($hoy)->startOfDay();

        if ($fecha->lessThanOrEqualTo($origen)) {
            return false;
        }

        if ($fecha->day !== $origen->day) {
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
