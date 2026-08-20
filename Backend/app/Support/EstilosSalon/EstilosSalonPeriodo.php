<?php

namespace App\Support\EstilosSalon;

use Carbon\Carbon;
use DateTimeInterface;

final class EstilosSalonPeriodo
{
    public const EMPRESAS_IDS = [397, 396, 398, 428, 427, 429, 432, 543, 657, 690, 488];

    public static function empresaPermitida(int $idEmpresa): bool
    {
        return in_array($idEmpresa, self::EMPRESAS_IDS, true);
    }

    public static function esDiaEnvio(DateTimeInterface $fecha): bool
    {
        $carbon = Carbon::instance($fecha);

        return in_array($carbon->day, self::cortesDelMes($carbon), true);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function rangoAcumulado(DateTimeInterface $fecha): array
    {
        $carbon = Carbon::instance($fecha);

        return [
            $carbon->copy()->startOfMonth()->format('Y-m-d'),
            $carbon->format('Y-m-d'),
        ];
    }

    /**
     * Del 1 al último corte ya cerrado. Si aún no hay corte, sugiere el primero del mes.
     *
     * @return array{0: string, 1: string}
     */
    public static function rangoSugerido(DateTimeInterface $fecha): array
    {
        $carbon = Carbon::instance($fecha);
        $corte = self::cortesDelMes($carbon)[0];

        foreach (self::cortesDelMes($carbon) as $diaCorte) {
            if ($diaCorte <= $carbon->day) {
                $corte = $diaCorte;
            }
        }

        return [
            $carbon->copy()->startOfMonth()->format('Y-m-d'),
            $carbon->copy()->day($corte)->format('Y-m-d'),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function periodoCron(DateTimeInterface $fecha): ?array
    {
        if (! self::esDiaEnvio($fecha)) {
            return null;
        }

        return self::rangoAcumulado($fecha);
    }

    /**
     * @return list<int>
     */
    private static function cortesDelMes(Carbon $fecha): array
    {
        if ($fecha->month === 2) {
            return [6, 15, 21, 28];
        }

        if ($fecha->daysInMonth === 30) {
            return [7, 15, 22, 30];
        }

        return [8, 15, 23, 31];
    }
}
