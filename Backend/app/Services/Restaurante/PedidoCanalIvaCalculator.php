<?php

namespace App\Services\Restaurante;

/**
 * Display IVA for canal orders. Stored pedido prices stay sin IVA (facturación SoT).
 */
class PedidoCanalIvaCalculator
{
    /**
     * @param  iterable<int, object|array<string, mixed>>  $detalles
     * @return array{
     *     lineas: list<array{precio_con_iva: float, total_con_iva: float, iva: float, porcentaje_impuesto: float}>,
     *     subtotal: float,
     *     descuento: float,
     *     iva: float,
     *     total_con_iva: float
     * }
     */
    public static function calcular(iterable $detalles, float $ivaEmpresa): array
    {
        $ivaEmpresa = max(0, $ivaEmpresa);
        $lineas = [];
        $subtotal = 0.0;
        $descuentoTotal = 0.0;
        $totalConIva = 0.0;

        foreach ($detalles as $d) {
            $cantidad = (float) self::val($d, 'cantidad', 0);
            $precio = (float) self::val($d, 'precio', 0);
            $descuento = (float) self::val($d, 'descuento', 0);

            $pct = self::pct($d, $ivaEmpresa);
            $factor = $pct > 0 ? (1 + $pct / 100) : 1.0;

            $subtotalLinea = self::redondearMoneda($cantidad * $precio);
            $base = self::redondearMoneda($cantidad * $precio - $descuento);
            $precioConIvaUnrounded = $pct > 0 ? $precio * $factor : $precio;
            $descuentoConIva = $pct > 0 ? $descuento * $factor : $descuento;
            $precioConIva = self::redondearMoneda($precioConIvaUnrounded);
            $lineaConIva = self::redondearMoneda($cantidad * $precioConIvaUnrounded - $descuentoConIva);
            $lineaIva = self::redondearMoneda($lineaConIva - $base);

            $lineas[] = [
                'precio_con_iva' => $precioConIva,
                'total_con_iva' => $lineaConIva,
                'iva' => $lineaIva,
                'porcentaje_impuesto' => $pct,
            ];

            $subtotal += $subtotalLinea;
            $descuentoTotal += self::redondearMoneda($descuento);
            $totalConIva += $lineaConIva;
        }

        $subtotal = self::redondearMoneda($subtotal);
        $descuentoTotal = self::redondearMoneda($descuentoTotal);
        $totalConIva = self::redondearMoneda($totalConIva);
        $ivaTotal = self::redondearMoneda($totalConIva - ($subtotal - $descuentoTotal));

        return [
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'descuento' => $descuentoTotal,
            'iva' => $ivaTotal,
            'total_con_iva' => $totalConIva,
        ];
    }

    public static function ivaEmpresa(?object $empresa): float
    {
        if (! $empresa) {
            return 0.0;
        }

        return max(0, (float) ($empresa->iva ?? 0));
    }

    /** Same cent rounding as Frontend redondearMoneda (facturación v2). */
    public static function redondearMoneda(float $n): float
    {
        if (is_nan($n) || is_infinite($n)) {
            return 0.0;
        }
        $sign = $n < 0 ? -1.0 : 1.0;
        $cents = round(abs($n) * 100 + 1e-10, 0);

        return $sign * $cents / 100.0;
    }

    /**
     * @param  object|array<string, mixed>  $detalle
     */
    private static function pct(object|array $detalle, float $ivaEmpresa): float
    {
        $producto = self::val($detalle, 'producto', null);
        if ($producto === null) {
            return $ivaEmpresa;
        }

        $raw = self::val($producto, 'porcentaje_impuesto', null);
        if ($raw === null || $raw === '') {
            return $ivaEmpresa;
        }

        return max(0, (float) $raw);
    }

    /**
     * @param  object|array<string, mixed>  $row
     */
    private static function val(object|array $row, string $key, mixed $default): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        return $row->{$key} ?? $default;
    }
}
