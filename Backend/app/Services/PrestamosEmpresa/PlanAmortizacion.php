<?php

namespace App\Services\PrestamosEmpresa;

use Carbon\Carbon;
use InvalidArgumentException;

class PlanAmortizacion
{
    /**
     * @return list<array{numero: int, fecha_vencimiento: string, capital: float, interes: float, total: float}>
     */
    public static function iguales(float $principal, int $nCuotas, string $fechaPrimera, string $frecuencia): array
    {
        self::assertEntrada($principal, $nCuotas, $frecuencia);

        $base = round($principal / $nCuotas, 2);
        $acumulado = 0.0;
        $cuotas = [];
        $inicio = Carbon::parse($fechaPrimera)->startOfDay();

        for ($i = 1; $i <= $nCuotas; $i++) {
            $capital = $i === $nCuotas ? round($principal - $acumulado, 2) : $base;
            $acumulado = round($acumulado + $capital, 2);
            $cuotas[] = [
                'numero' => $i,
                'fecha_vencimiento' => self::fechaCuota($inicio, $i - 1, $frecuencia),
                'capital' => $capital,
                'interes' => 0.0,
                'total' => $capital,
            ];
        }

        return $cuotas;
    }

    /**
     * @return list<array{numero: int, fecha_vencimiento: string, capital: float, interes: float, total: float}>
     */
    public static function francesa(float $principal, float $tasaAnualPct, int $nCuotas, string $fechaPrimera, string $frecuencia): array
    {
        self::assertEntrada($principal, $nCuotas, $frecuencia);

        if ($tasaAnualPct <= 0) {
            return self::iguales($principal, $nCuotas, $fechaPrimera, $frecuencia);
        }

        $r = ($tasaAnualPct / 100) / self::periodosPorAnio($frecuencia);
        $factor = pow(1 + $r, $nCuotas);
        $pmt = round($principal * $r * $factor / ($factor - 1), 2);
        $saldo = $principal;
        $inicio = Carbon::parse($fechaPrimera)->startOfDay();
        $cuotas = [];

        for ($i = 1; $i <= $nCuotas; $i++) {
            $interes = round($saldo * $r, 2);
            if ($i === $nCuotas) {
                $capital = round($saldo, 2);
                $total = round($capital + $interes, 2);
            } else {
                $capital = round($pmt - $interes, 2);
                $total = $pmt;
            }
            $saldo = round($saldo - $capital, 2);
            $cuotas[] = [
                'numero' => $i,
                'fecha_vencimiento' => self::fechaCuota($inicio, $i - 1, $frecuencia),
                'capital' => $capital,
                'interes' => $interes,
                'total' => $total,
            ];
        }

        return $cuotas;
    }

    /** @param  list<array{total: float}>  $filas */
    public static function montoACobrar(array $filas, float $saldo): float
    {
        $suma = round(array_sum(array_column($filas, 'total')), 2);

        return min($suma, round($saldo, 2));
    }

    public static function clasificacion(int $nCuotas, string $frecuencia): string
    {
        return $nCuotas > self::periodosPorAnio($frecuencia) ? 'largo' : 'corto';
    }

    public static function periodosPorAnio(string $frecuencia): int
    {
        return match ($frecuencia) {
            'semanal' => 52,
            'quincenal' => 26,
            'mensual' => 12,
            default => throw new InvalidArgumentException('Frecuencia inválida.'),
        };
    }

    public static function generar(bool $generaInteres, float $principal, float $tasaAnualPct, int $nCuotas, string $fechaPrimera, string $frecuencia): array
    {
        return $generaInteres
            ? self::francesa($principal, $tasaAnualPct, $nCuotas, $fechaPrimera, $frecuencia)
            : self::iguales($principal, $nCuotas, $fechaPrimera, $frecuencia);
    }

    private static function fechaCuota(Carbon $inicio, int $offset, string $frecuencia): string
    {
        $fecha = $inicio->copy();
        if ($frecuencia === 'mensual') {
            $fecha->addMonthsNoOverflow($offset);
        } elseif ($frecuencia === 'quincenal') {
            $fecha->addDays($offset * 14);
        } else {
            $fecha->addDays($offset * 7);
        }

        return $fecha->toDateString();
    }

    private static function assertEntrada(float $principal, int $nCuotas, string $frecuencia): void
    {
        if ($principal <= 0 || $nCuotas < 2) {
            throw new InvalidArgumentException('El préstamo requiere monto > 0 y al menos 2 cuotas.');
        }
        self::periodosPorAnio($frecuencia);
    }
}
