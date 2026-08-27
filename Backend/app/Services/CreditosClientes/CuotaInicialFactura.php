<?php

namespace App\Services\CreditosClientes;

class CuotaInicialFactura
{
    public static function coincide(float $totalVenta, float $montoPrimeraCuota): bool
    {
        return round($totalVenta, 2) === round($montoPrimeraCuota, 2);
    }
}
