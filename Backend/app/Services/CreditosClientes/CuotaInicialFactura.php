<?php

namespace App\Services\CreditosClientes;

class CuotaInicialFactura
{
    public static function coincide(float $totalVenta, float $montoContrato, int $nCuotas): bool
    {
        $plan = PlanCuotasIguales::generar($montoContrato, $nCuotas, '2000-01-01');

        return round($totalVenta, 2) === $plan[0]['monto'];
    }
}
