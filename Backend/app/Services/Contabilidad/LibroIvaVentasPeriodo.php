<?php

namespace App\Services\Contabilidad;

/**
 * Columnas del resumen fiscal.
 * ponytail: no seleccionar dte; el sello se filtra en SQL para no leer S3 por venta.
 */
final class LibroIvaVentasPeriodo
{
    /** @return list<string> */
    public static function columnasSinDte(): array
    {
        return [
            'ventas.id',
            'ventas.id_documento',
            'ventas.fecha',
            'ventas.total',
            'ventas.iva',
            'ventas.iva_retenido',
            'ventas.iva_percibido',
            'ventas.sub_total',
            'ventas.exenta',
            'ventas.gravada',
            'ventas.no_sujeta',
            'ventas.cuenta_a_terceros',
        ];
    }

    /** @return list<string> */
    public static function columnasDevolucionSinDte(): array
    {
        return [
            'devoluciones_venta.id',
            'devoluciones_venta.id_venta',
            'devoluciones_venta.total',
            'devoluciones_venta.iva',
            'devoluciones_venta.iva_retenido',
            'devoluciones_venta.iva_percibido',
            'devoluciones_venta.sub_total',
            'devoluciones_venta.exenta',
            'devoluciones_venta.no_sujeta',
            'devoluciones_venta.cuenta_a_terceros',
        ];
    }
}
