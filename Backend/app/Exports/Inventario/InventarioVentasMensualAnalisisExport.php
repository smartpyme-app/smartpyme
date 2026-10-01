<?php

namespace App\Exports\Inventario;

/**
 * @deprecated Use InventarioVentasMensualAnalisisWorkbookExport. Kept for metricasDesdeKardex() in tests.
 */
class InventarioVentasMensualAnalisisExport
{
    /**
     * @return array{0: float, 1: float, 2: float, 3: float|string, 4: float|string}
     */
    public static function metricasDesdeKardex(
        float $salidaValor,
        float $descuento,
        float $salidaCantidad,
        float $costoSalida,
        ?float $costoRetaceo
    ): array {
        return InventarioVentasMensualAnalisisReport::metricasDesdeKardex(
            $salidaValor,
            $descuento,
            $salidaCantidad,
            $costoSalida,
            $costoRetaceo
        );
    }
}
