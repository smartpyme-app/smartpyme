<?php

/**
 * Self-check: php Backend/scripts/check_reporte_periodo.php
 * Falla (exit 1) si el cálculo de períodos relativos se rompe.
 */

require __DIR__.'/../vendor/autoload.php';

use App\Support\ReportePeriodo;
use Carbon\Carbon;

function assertEq($label, $got, $expected)
{
    if ($got !== $expected) {
        fwrite(STDERR, "FAIL $label: got ".json_encode($got).' expected '.json_encode($expected)."\n");
        exit(1);
    }
    echo "OK $label\n";
}

$ref = Carbon::create(2026, 7, 28); // martes

assertEq('null→hoy', ReportePeriodo::rango(null, $ref), ['2026-07-28', '2026-07-28']);
assertEq('hoy', ReportePeriodo::rango('hoy', $ref), ['2026-07-28', '2026-07-28']);
assertEq('ultimos3', ReportePeriodo::rango('ultimos3', $ref), ['2026-07-26', '2026-07-28']);
assertEq('ultimos7', ReportePeriodo::rango('ultimos7', $ref), ['2026-07-22', '2026-07-28']);
assertEq('ultimos15', ReportePeriodo::rango('ultimos15', $ref), ['2026-07-14', '2026-07-28']);
assertEq('mes', ReportePeriodo::rango('mes', $ref), ['2026-07-01', '2026-07-28']);
assertEq('ultimos3Meses', ReportePeriodo::rango('ultimos3Meses', $ref), ['2026-05-01', '2026-07-28']);
assertEq('ultimos6Meses', ReportePeriodo::rango('ultimos6Meses', $ref), ['2026-02-01', '2026-07-28']);
assertEq('anio', ReportePeriodo::rango('anio', $ref), ['2026-01-01', '2026-07-28']);

// legacy aún resuelve
assertEq('ayer', ReportePeriodo::rango('ayer', $ref), ['2026-07-27', '2026-07-27']);
assertEq('semanaAnterior', ReportePeriodo::rango('semanaAnterior', $ref), ['2026-07-20', '2026-07-26']);

echo "All checks passed.\n";
exit(0);
