<?php

namespace App\Services\Ventas;

use Carbon\Carbon;

class ProgramacionCalendario
{
    public static function periodo(string $frecuencia, Carbon|string $cuando): string
    {
        $fecha = $cuando instanceof Carbon ? $cuando : Carbon::parse($cuando);

        return match ($frecuencia) {
            'mensual' => $fecha->format('Y-m'),
            'anual' => $fecha->format('Y'),
        };
    }

    public static function calcularProxima(string $frecuencia, int $dia, string $hora, Carbon $desde): Carbon
    {
        $tz = $desde->timezone;
        $parts = self::parseHora($hora);
        $candidate = self::slotEnMes($desde->year, $desde->month, $dia, $parts, $tz);

        while ($candidate->lessThan($desde)) {
            $candidate = self::avanzarCiclo($candidate, $frecuencia, $dia, $parts);
        }

        return $candidate;
    }

    public static function siguienteTrasEjecucion(string $frecuencia, int $dia, string $hora, Carbon $ejecutadaEn): Carbon
    {
        $parts = self::parseHora($hora);

        return self::avanzarCiclo($ejecutadaEn->copy()->timezone($ejecutadaEn->timezone), $frecuencia, $dia, $parts);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function parseHora(string $hora): array
    {
        [$h, $m, $s] = array_pad(explode(':', $hora), 3, '0');

        return [(int) $h, (int) $m, (int) $s];
    }

    private static function slotEnMes(int $year, int $month, int $dia, array $parts, $tz): Carbon
    {
        $base = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
        $day = min($dia, $base->daysInMonth);

        return Carbon::create($year, $month, $day, $parts[0], $parts[1], $parts[2], $tz);
    }

    private static function avanzarCiclo(Carbon $ref, string $frecuencia, int $dia, array $parts): Carbon
    {
        $tz = $ref->timezone;
        if ($frecuencia === 'anual') {
            return self::slotEnMes($ref->year + 1, $ref->month, $dia, $parts, $tz);
        }

        $month = $ref->month + 1;
        $year = $ref->year;
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return self::slotEnMes($year, $month, $dia, $parts, $tz);
    }
}