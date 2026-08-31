<?php

namespace App\Exports\Support;

class DevolucionEnReporte
{
    public const ORIGEN = 'devolucion';

    public const ESTADO = 'Devolución';

    public static function negar($valor): float
    {
        $n = round((float) $valor, 2);
        if (abs($n) < 0.00001) {
            return 0.0;
        }

        return $n > 0 ? -$n : $n;
    }

    public static function marcar(object $row): object
    {
        $row->origen_export = self::ORIGEN;

        return $row;
    }

    public static function esDevolucion($row): bool
    {
        if (!is_object($row)) {
            return false;
        }

        return ($row->origen_export ?? null) === self::ORIGEN;
    }

    /**
     * @return array{
     *     costo: float,
     *     cuenta_terceros: float,
     *     sub_total: float,
     *     descuento: float,
     *     iva: float,
     *     utilidad: float,
     *     total_sin_iva: float,
     *     total: float,
     *     propina: float
     * }
     */
    public static function montosVentaNegados(object $devolucion): array
    {
        $costo = (float) ($devolucion->total_costo ?? 0);
        $sub = (float) ($devolucion->sub_total ?? 0);
        $desc = (float) ($devolucion->descuento ?? 0);
        $iva = (float) ($devolucion->iva ?? 0);
        $total = (float) ($devolucion->total ?? 0);
        $cuenta = (float) ($devolucion->cuenta_a_terceros ?? 0);
        $propina = (float) ($devolucion->propina ?? 0);

        return [
            'costo' => self::negar($costo),
            'cuenta_terceros' => self::negar($cuenta),
            'sub_total' => self::negar($sub),
            'descuento' => self::negar($desc),
            'iva' => self::negar($iva),
            'utilidad' => self::negar($total - $costo - $iva),
            'total_sin_iva' => self::negar($sub - $desc),
            'total' => self::negar($total),
            'propina' => self::negar($propina),
        ];
    }

    /**
     * @return array{
     *     cantidad: float,
     *     costo: float,
     *     precio: float,
     *     descuento: float,
     *     iva: float,
     *     utilidad: float,
     *     total: float
     * }
     */
    public static function montosDetalleNegados(object $detalle, float $ivaLinea = 0): array
    {
        $cantidad = (float) ($detalle->cantidad ?? 0);
        $costo = (float) ($detalle->costo ?? 0);
        $precio = (float) ($detalle->precio ?? 0);
        $descuento = (float) ($detalle->descuento ?? 0);
        $total = (float) ($detalle->total ?? 0);

        return [
            'cantidad' => self::negar($cantidad),
            'costo' => round($costo, 2),
            'precio' => round($precio, 2),
            'descuento' => self::negar($descuento),
            'iva' => self::negar($ivaLinea),
            'utilidad' => self::negar($total - ($costo * $cantidad)),
            'total' => self::negar($total + $ivaLinea),
        ];
    }
}
