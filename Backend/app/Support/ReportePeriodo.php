<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Resuelve períodos relativos de reportes automatizados.
 * Claves alineadas con seleccionarPeriodo() del frontend.
 */
class ReportePeriodo
{
    /** Catálogo UI actual + claves legacy (configs ya guardadas). */
    public const PERIODOS = [
        'hoy',
        'ultimos3',
        'ultimos7',
        'ultimos15',
        'mes',
        'ultimos3Meses',
        'ultimos6Meses',
        'anio',
        // legacy
        'ayer',
        'semana',
        'semanaAnterior',
        'ultimas2Semanas',
        'mesAnterior',
        'trimestre',
        'trimestreAnterior',
        'anioAnterior',
    ];

    /**
     * @return array{0: string, 1: string} [fecha_inicio, fecha_fin] Y-m-d
     */
    public static function rango(?string $periodo, ?Carbon $referencia = null): array
    {
        $hoy = ($referencia ?? Carbon::today())->copy()->startOfDay();
        $inicio = $hoy->copy();
        $fin = $hoy->copy();
        $periodo = $periodo ?: 'hoy';

        switch ($periodo) {
            case 'hoy':
                break;

            case 'ayer':
                $inicio = $hoy->copy()->subDay();
                $fin = $inicio->copy();
                break;

            case 'ultimos3':
                $inicio = $hoy->copy()->subDays(2);
                break;

            case 'ultimos7':
                $inicio = $hoy->copy()->subDays(6);
                break;

            case 'ultimos15':
                $inicio = $hoy->copy()->subDays(14);
                break;

            case 'semana':
                $inicio = $hoy->copy()->startOfWeek(Carbon::MONDAY);
                break;

            case 'semanaAnterior':
                $inicio = $hoy->copy()->startOfWeek(Carbon::MONDAY)->subWeek();
                $fin = $inicio->copy()->addDays(6);
                break;

            case 'ultimas2Semanas':
                $inicio = $hoy->copy()->subDays(13);
                break;

            case 'mes':
                $inicio = $hoy->copy()->startOfMonth();
                break;

            case 'mesAnterior':
                $inicio = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
                $fin = $inicio->copy()->endOfMonth()->startOfDay();
                break;

            case 'ultimos3Meses':
                $inicio = $hoy->copy()->subMonthsNoOverflow(2)->startOfMonth();
                break;

            case 'ultimos6Meses':
                $inicio = $hoy->copy()->subMonthsNoOverflow(5)->startOfMonth();
                break;

            case 'trimestre':
                // FE: Math.floor(month0 / 3)
                $q = (int) floor(($hoy->month - 1) / 3);
                $inicio = $hoy->copy()->month($q * 3 + 1)->day(1)->startOfDay();
                $fin = $inicio->copy()->addMonths(3)->subDay();
                break;

            case 'trimestreAnterior':
                // Mirror FE: Math.floor((month0 - 3) / 3) * 3
                $month0 = $hoy->month - 1;
                $trimestreAnteriorMes = (int) floor(($month0 - 3) / 3) * 3;
                $anio = $hoy->year + (int) floor($trimestreAnteriorMes / 12);
                $mes0 = (($trimestreAnteriorMes % 12) + 12) % 12;
                $inicio = Carbon::create($anio, $mes0 + 1, 1)->startOfDay();
                $fin = $inicio->copy()->addMonths(3)->subDay();
                break;

            case 'anio':
                $inicio = $hoy->copy()->startOfYear();
                $fin = $hoy->copy();
                break;

            case 'anioAnterior':
                $inicio = $hoy->copy()->subYear()->startOfYear();
                $fin = $hoy->copy()->subYear()->endOfYear()->startOfDay();
                break;

            default:
                // ponytail: período desconocido → hoy (mismo default de configs legacy)
                break;
        }

        return [$inicio->format('Y-m-d'), $fin->format('Y-m-d')];
    }
}
