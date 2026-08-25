<?php

namespace App\Services\CreditosClientes;

use Carbon\Carbon;
use InvalidArgumentException;

class PlanCuotasIguales
{
    /**
     * @return list<array{numero: int, monto: float, fecha_vencimiento: string}>
     */
    public static function generar(float $monto, int $nCuotas, string $fechaInicio): array
    {
        if ($nCuotas < 2 || $monto <= 0) {
            throw new InvalidArgumentException('El crédito requiere monto > 0 y al menos 2 cuotas.');
        }

        $base = round($monto / $nCuotas, 2);
        $cuotas = [];
        $acumulado = 0.0;
        $inicio = Carbon::parse($fechaInicio)->startOfDay();

        for ($i = 1; $i <= $nCuotas; $i++) {
            $montoCuota = $i === $nCuotas
                ? round($monto - $acumulado, 2)
                : $base;
            $acumulado = round($acumulado + $montoCuota, 2);

            $cuotas[] = [
                'numero' => $i,
                'monto' => $montoCuota,
                'fecha_vencimiento' => $inicio->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
            ];
        }

        return $cuotas;
    }
}
