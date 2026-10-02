<?php

namespace App\Services\Contabilidad\Partidas;

class ReglaIngresoVenta
{
    /**
     * Venta pendiente de pago: el Debe es cuentas por cobrar.
     * Venta cobrada: el Debe es la cuenta del banco de la forma de pago.
     */
    public static function origenCuentaDebe(object $venta): string
    {
        return ($venta->estado ?? null) === 'Pendiente' ? 'cxc' : 'forma_pago';
    }

    /**
     * @param  array<int|string>  $idsTodos
     * @param  array<int|string>  $idsYaContabilizados
     * @return list<int>
     */
    public static function idsSinPartida(array $idsTodos, array $idsYaContabilizados): array
    {
        $todos = array_values(array_unique(array_filter(array_map('intval', $idsTodos))));
        $ya = array_map('intval', $idsYaContabilizados);

        return array_values(array_diff($todos, $ya));
    }
}
