<?php

namespace App\Services\Contabilidad;

use Carbon\Carbon;

/**
 * Calendario fiscal: mes de inicio configurable, etiqueta de ejercicio = año calendario del último mes del ejercicio.
 */
class FiscalYearCalendar
{
    public static function mesInicioNormalizado(int $mes): int
    {
        if ($mes < 1) {
            return 1;
        }
        if ($mes > 12) {
            return 12;
        }

        return $mes;
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} [inicio, fin]
     */
    public static function rangoEjercicio(int $mesInicio, int $anioReferencia): array
    {
        $mesInicio = self::mesInicioNormalizado($mesInicio);
        $finMes = $mesInicio === 1 ? 12 : $mesInicio - 1;
        $inicioAnio = $mesInicio === 1 ? $anioReferencia : $anioReferencia - 1;

        $inicio = Carbon::create($inicioAnio, $mesInicio, 1)->startOfDay();
        $fin = Carbon::create($anioReferencia, $finMes, 1)->endOfMonth()->endOfDay();

        return [$inicio, $fin];
    }

    /**
     * @return list<array{year: int, month: int}>
     */
    public static function periodosEnEjercicio(int $mesInicio, int $anioReferencia): array
    {
        $mesInicio = self::mesInicioNormalizado($mesInicio);
        $finMes = $mesInicio === 1 ? 12 : $mesInicio - 1;
        $inicioAnio = $mesInicio === 1 ? $anioReferencia : $anioReferencia - 1;

        $cur = Carbon::create($inicioAnio, $mesInicio, 1)->startOfMonth();
        $last = Carbon::create($anioReferencia, $finMes, 1)->startOfMonth();
        $out = [];

        while ($cur->lte($last)) {
            $out[] = ['year' => (int) $cur->year, 'month' => (int) $cur->month];
            $cur->addMonth();
        }

        return $out;
    }

    public static function anioReferenciaParaFecha(int $mesInicio, Carbon $fecha): int
    {
        $mesInicio = self::mesInicioNormalizado($mesInicio);
        $y = (int) $fecha->year;
        $m = (int) $fecha->month;

        if ($mesInicio === 1) {
            return $y;
        }

        if ($m >= $mesInicio) {
            return $y + 1;
        }

        return $y;
    }

    /**
     * @return array{year: int, month: int}
     */
    public static function ultimoMesEjercicio(int $mesInicio, int $anioReferencia): array
    {
        $mesInicio = self::mesInicioNormalizado($mesInicio);
        $finMes = $mesInicio === 1 ? 12 : $mesInicio - 1;

        return ['year' => $anioReferencia, 'month' => $finMes];
    }
}
