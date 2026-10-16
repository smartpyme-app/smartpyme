<?php

namespace App\Services\Contabilidad\Partidas;

/**
 * Monto al Haber en cuentas de ingreso por línea de venta (sin IVA; el IVA va en su propia línea).
 */
class ReglaMontoIngresoVentaDetalle
{
    /** Columnas mínimas al eager-load de detalles_venta (ingresos / CxC del día). */
    public const COLUMNAS_DETALLE_VENTA = [
        'id',
        'id_venta',
        'id_producto',
        'total',
        'costo',
        'cantidad',
        'gravada',
        'exenta',
        'no_sujeta',
        'iva',
        'sub_total',
    ];

    public static function haber(object $detalle): float
    {
        $base = (float) ($detalle->gravada ?? 0)
            + (float) ($detalle->exenta ?? 0)
            + (float) ($detalle->no_sujeta ?? 0);

        if ($base > 0) {
            return round($base, 2);
        }

        $total = (float) ($detalle->total ?? 0);
        $iva = (float) ($detalle->iva ?? 0);
        $totalIva = (float) ($detalle->total_iva ?? 0);

        if ($totalIva > 0.001) {
            if (abs($totalIva - $total) < 0.015) {
                return round(max(0, $total - $iva), 2);
            }

            return round($total, 2);
        }

        if ($iva > 0.001 && $total > $iva) {
            $neto = $total - $iva;
            if ($neto > 0.001 && abs(($total / $neto) - 1.13) < 0.015) {
                return round($neto, 2);
            }
        }

        if (isset($detalle->sub_total) && is_numeric($detalle->sub_total)) {
            return round((float) $detalle->sub_total, 2);
        }

        return round($total, 2);
    }
}
