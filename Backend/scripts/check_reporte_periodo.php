<?php

/**
 * Self-check: php84 Backend/scripts/check_reporte_periodo.php
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
assertEq('ayer', ReportePeriodo::rango('ayer', $ref), ['2026-07-27', '2026-07-27']);
assertEq('ultimos7', ReportePeriodo::rango('ultimos7', $ref), ['2026-07-22', '2026-07-28']);
assertEq('semana', ReportePeriodo::rango('semana', $ref), ['2026-07-27', '2026-07-28']); // lun 27
assertEq('semanaAnterior', ReportePeriodo::rango('semanaAnterior', $ref), ['2026-07-20', '2026-07-26']);
assertEq('mes', ReportePeriodo::rango('mes', $ref), ['2026-07-01', '2026-07-28']);
assertEq('mesAnterior', ReportePeriodo::rango('mesAnterior', $ref), ['2026-06-01', '2026-06-30']);
assertEq('trimestre', ReportePeriodo::rango('trimestre', $ref), ['2026-07-01', '2026-09-30']);
assertEq('anioAnterior', ReportePeriodo::rango('anioAnterior', $ref), ['2025-01-01', '2025-12-31']);

$q1 = Carbon::create(2026, 2, 10);
assertEq('trimestreAnterior-from-Q1', ReportePeriodo::rango('trimestreAnterior', $q1), ['2025-10-01', '2025-12-31']);

echo "All checks passed.\n";
exit(0);
