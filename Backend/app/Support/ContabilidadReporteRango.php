<?php

namespace App\Support;

use Carbon\Carbon;

final class ContabilidadReporteRango
{
    /** ponytail: techo PDF contable; rangos mayores deben usar Excel en cola. */
    public const PDF_MAX_DAYS = 31;

    public static function diasEnRango(string $fechaInicio, string $fechaFin): int
    {
        $start = Carbon::parse($fechaInicio)->startOfDay();
        $end = Carbon::parse($fechaFin)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    public static function pdfPermitido(string $fechaInicio, string $fechaFin): bool
    {
        return self::diasEnRango($fechaInicio, $fechaFin) <= self::PDF_MAX_DAYS;
    }
}
