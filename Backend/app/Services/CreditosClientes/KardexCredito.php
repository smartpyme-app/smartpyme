<?php

namespace App\Services\CreditosClientes;

class KardexCredito
{
    public static function debeMoverInventario($tipo, $numeroCuota): bool
    {
        return $tipo === 'bien' && (int) $numeroCuota === 1;
    }
}
