<?php

namespace App\Services\CreditosClientes;

class CuotasRestantes
{
    /**
     * @param  list<array{numero: int, monto?: float, fecha_vencimiento: string, id_venta?: mixed}>  $plan
     * @return list<array<string, mixed>>
     */
    public static function dePlan(array $plan): array
    {
        return array_values(array_filter(
            $plan,
            fn (array $cuota) => (int) ($cuota['numero'] ?? 0) > 1 && empty($cuota['id_venta'])
        ));
    }
}
