<?php

namespace App\Services\CreditosClientes;

class CupoCredito
{
    public static function cabe(?float $limite, float $saldoUsado, float $montoNuevo): bool
    {
        if ($limite === null || $limite <= 0) {
            return true;
        }

        return round($saldoUsado + $montoNuevo, 2) <= round($limite, 2);
    }
}
