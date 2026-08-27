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

    /**
     * @param  list<array{numero: int, monto: float, fecha_vencimiento: string}>  $plan
     * @param  list<array{monto?: mixed}|mixed>  $montos
     * @return list<array{numero: int, monto: float, fecha_vencimiento: string}>
     */
    public static function aplicarMontos(array $plan, array $montos, float $montoContrato): array
    {
        if (count($montos) !== count($plan)) {
            throw new InvalidArgumentException('El número de montos no coincide con las cuotas.');
        }

        $suma = 0.0;
        foreach ($plan as $i => $cuota) {
            $raw = is_array($montos[$i]) ? ($montos[$i]['monto'] ?? 0) : $montos[$i];
            $monto = round((float) $raw, 2);
            if ($monto <= 0) {
                throw new InvalidArgumentException('Cada cuota debe ser mayor a 0.');
            }
            $plan[$i]['monto'] = $monto;
            $suma = round($suma + $monto, 2);
        }

        if ($suma !== round($montoContrato, 2)) {
            throw new InvalidArgumentException('La suma de las cuotas debe ser igual al monto del contrato.');
        }

        return $plan;
    }
}
