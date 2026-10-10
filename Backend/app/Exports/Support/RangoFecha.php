<?php

namespace App\Exports\Support;

use Carbon\Carbon;

/**
 * Un fin en formato Y-m-d incluye ese día completo.
 * `fecha <= '2026-08-31'` en un datetime se queda en las 00:00.
 */
class RangoFecha
{
    public static function aplicar($query, string $columna, $inicio, $fin)
    {
        if ($inicio !== null && $inicio !== '') {
            $query->where($columna, '>=', $inicio);
        }

        $cota = self::cotaFin($fin);
        if ($cota !== null) {
            $query->where($columna, $cota[0], $cota[1]);
        }

        return $query;
    }

    public static function ventasDelPeriodo($query, string $alias, $inicio, $fin)
    {
        $query->where($alias . '.estado', '!=', 'Anulada')
            ->where($alias . '.cotizacion', 0);

        return self::aplicar($query, $alias . '.fecha', $inicio, $fin);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function cotaFin($fin): ?array
    {
        if ($fin === null || $fin === '') {
            return null;
        }

        $fin = trim((string) $fin);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) {
            return ['<', Carbon::parse($fin)->addDay()->toDateString()];
        }

        return ['<=', $fin];
    }
}
