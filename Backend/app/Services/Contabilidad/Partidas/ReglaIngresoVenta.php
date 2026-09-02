<?php

namespace App\Services\Contabilidad\Partidas;

class ReglaIngresoVenta
{
    /**
     * Misma regla que la partida de ingreso del día: el Debe es la cuenta
     * de la forma de pago, tanto al contado como al crédito.
     */
    public static function origenCuentaDebe(object $venta): string
    {
        return 'forma_pago';
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
