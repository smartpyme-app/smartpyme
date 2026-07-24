<?php

namespace App\Services\FidelizacionCliente;

use App\Models\Ventas\Devoluciones\Devolucion;

/**
 * Compatibilidad: delega la sincronización de puntos por devolución a ReversionPuntosService.
 * (Proporcional a puntos_ganados / puntos_canjeados de la venta.)
 */
class DevolucionPuntosService
{
    private $reversionPuntosService;

    public function __construct(ReversionPuntosService $reversionPuntosService)
    {
        $this->reversionPuntosService = $reversionPuntosService;
    }

    /**
     * Sincroniza el ajuste de puntos asociado a una devolución (crear / actualizar / revertir).
     */
    public function syncPuntosParaDevolucion(Devolucion $devolucion): void
    {
        $this->reversionPuntosService->syncPorDevolucion($devolucion);
    }
}
