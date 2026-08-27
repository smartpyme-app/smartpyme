<?php

namespace App\Services\CreditosClientes;

use Carbon\Carbon;

class ColaCuotas
{
    public const VENTANA_DIAS = 7;

    public static function estadoCola($idVenta, string $fechaVencimiento, string $hoy): ?string
    {
        if ($idVenta) {
            return null;
        }

        if ($fechaVencimiento <= $hoy) {
            return 'vencida';
        }

        $limite = Carbon::parse($hoy)->addDays(self::VENTANA_DIAS)->toDateString();
        if ($fechaVencimiento <= $limite) {
            return 'por_facturar';
        }

        return null;
    }

    public static function estadoVisible($idVenta, string $fechaVencimiento, string $hoy): string
    {
        if ($idVenta) {
            return 'facturada';
        }

        return self::estadoCola($idVenta, $fechaVencimiento, $hoy) ?? 'programada';
    }
}
