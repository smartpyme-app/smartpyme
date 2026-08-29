<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\DivisionCuenta;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;

class PreCuentaSesionCleanup
{
    /** Elimina pre-cuentas pendientes huérfanas (p. ej. consumo ya vacío). */
    public static function anularPendientes(int $sesionId): void
    {
        $pendientes = PreCuenta::where('sesion_id', $sesionId)->where('estado', 'pendiente')->get();
        foreach ($pendientes as $pc) {
            PreCuentaOrdenDetalle::where('pre_cuenta_id', $pc->id)->delete();
            $pc->delete();
        }

        DivisionCuenta::where('sesion_id', $sesionId)
            ->whereDoesntHave('preCuentas')
            ->delete();
    }
}
