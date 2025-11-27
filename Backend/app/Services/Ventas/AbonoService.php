<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;

class AbonoService
{
    /**
     * Cancelar abonos de una venta
     *
     * @param Venta $venta
     * @return void
     */
    public function cancelarAbonos(Venta $venta): void
    {
        foreach ($venta->abonos as $abono) {
            $abono->estado = 'Cancelado';
            $abono->save();
        }
    }

    /**
     * Confirmar abonos de una venta
     *
     * @param Venta $venta
     * @return void
     */
    public function confirmarAbonos(Venta $venta): void
    {
        foreach ($venta->abonos as $abono) {
            $abono->estado = 'Confirmado';
            $abono->save();
        }
    }
}


