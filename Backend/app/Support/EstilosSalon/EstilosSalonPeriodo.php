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
        $dia = Carbon::instance($fecha)->day;
        $mes = Carbon::instance($fecha)->month;
        $diasDelMes = Carbon::instance($fecha)->daysInMonth;

        if ($mes === 2) {
            return in_array($dia, [6, 15, 21, 28], true);
        }

        if ($diasDelMes === 30) {
            return in_array($dia, [7, 15, 22, 30], true);
        }

        return in_array($dia, [8, 15, 23, 31], true);
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
     * @return array{0: string, 1: string}|null
     */
    public static function periodoCron(DateTimeInterface $fecha): ?array
    {
        if (! self::esDiaEnvio($fecha)) {
            return null;
        }

        return self::rangoAcumulado($fecha);
    }
}
