<?php

namespace App\Services\Inventario;

class ConversionInventarioService
{
    /**
     * Precisión de decimales utilizada en todo el sistema de presentaciones.
     */
    private const PRECISION = 6;

    /**
     * Convierte una cantidad expresada en un empaque alternativo
     * a su equivalente en unidades base.
     *
     * Ejemplo: 2 "Cajas de 30" × factor 30 = 60 unidades base.
     *
     * @param  float|int  $cantidadTransaccion  Cantidad del empaque (ej. 2 cajas).
     * @param  float|int|null  $factorConversion  Cuántas unidades base contiene el empaque.
     *                                            Si es null o 0 se asume 1 (unidad base directa).
     * @return float  Cantidad en unidades base redondeada a 6 decimales.
     */
    public static function calcularCantidadBase(
        $cantidadTransaccion,
        $factorConversion
    ): float {
        $factor = (float) ($factorConversion ?: 1);

        return round((float) $cantidadTransaccion * $factor, self::PRECISION);
    }

    /**
     * Costo total pagado de la fila de compra (cantidad × costo unitario − descuento).
     *
     * El descuento de línea debe restarse porque es lo que realmente se pagó
     * al proveedor; de lo contrario el costo de inventario queda inflado.
     *
     * @param  float|int  $cantidadTransaccion  Cantidad del empaque o unidades.
     * @param  float|int  $costoUnitario        Costo unitario de lista (antes de descuento).
     * @param  float|int  $descuento            Descuento de la fila (monto, no porcentaje).
     */
    public static function calcularCostoTotalFila(
        $cantidadTransaccion,
        $costoUnitario,
        $descuento = 0
    ): float {
        $bruto = (float) $cantidadTransaccion * (float) $costoUnitario;
        $neto = $bruto - (float) $descuento;

        return round(max(0.0, $neto), self::PRECISION);
    }

    /**
     * Costo por unidad base usando el neto pagado (incluye descuento de línea).
     *
     * Ejemplo: 10 unidades a $10 con $20 de descuento → $8.000000 por unidad base.
     *
     * @param  float|int       $cantidadTransaccion  Cantidad del empaque o unidades.
     * @param  float|int       $costoUnitario        Costo unitario de lista (antes de descuento).
     * @param  float|int       $descuento            Descuento de la fila (monto).
     * @param  float|int|null  $factorConversion     Factor del empaque; 1 si es unidad base.
     */
    public static function calcularCostoUnitarioNetoBase(
        $cantidadTransaccion,
        $costoUnitario,
        $descuento = 0,
        $factorConversion = 1
    ): float {
        $cantidadBase = self::calcularCantidadBase($cantidadTransaccion, $factorConversion);
        $costoTotal = self::calcularCostoTotalFila($cantidadTransaccion, $costoUnitario, $descuento);

        return self::calcularCostoUnitarioBase($costoTotal, $cantidadBase);
    }

    /**
     * Calcula el costo unitario de cada unidad base a partir del costo
     * total pagado por un conjunto de unidades base en una transacción.
     *
     * Se usa para alimentar el Kardex con el costo correcto por átomo,
     * independientemente del empaque con el que se compró.
     *
     * Ejemplo: Se pagaron $120 por 60 unidades base → costo unitario = $2.000000.
     *
     * @param  float|int  $costoTotalFila      Costo total de la fila de compra (neto pagado).
     * @param  float|int  $cantidadBaseTotal   Total de unidades base resultantes de la conversión.
     * @return float  Costo por unidad base redondeado a 6 decimales.
     *                Retorna 0.0 si cantidadBaseTotal es 0 para evitar división por cero.
     */
    public static function calcularCostoUnitarioBase(
        $costoTotalFila,
        $cantidadBaseTotal
    ): float {
        if ((float) $cantidadBaseTotal == 0.0) {
            return 0.0;
        }

        return round((float) $costoTotalFila / (float) $cantidadBaseTotal, self::PRECISION);
    }

    /**
     * Obtiene el precio de venta implícito de cada unidad base
     * a partir del precio de venta configurado para un empaque alternativo.
     *
     * Útil para calcular el precio base en el Kardex o al dividir
     * un empaque vendido en sus átomos para descontar stock correctamente.
     *
     * Ejemplo: Una "Caja de 30" se vende a $60 → precio unitario base = $2.000000.
     *
     * @param  float|int       $precioVentaEmpaque  Precio de venta del empaque alternativo.
     * @param  float|int|null  $factorConversion    Factor del empaque (unidades base que contiene).
     *                                              Si es null o 0 se asume 1.
     * @return float  Precio de la unidad base redondeado a 6 decimales.
     */
    public static function calcularPrecioUnitarioBase(
        $precioVentaEmpaque,
        $factorConversion
    ): float {
        $factor = (float) ($factorConversion ?: 1);

        return round((float) $precioVentaEmpaque / $factor, self::PRECISION);
    }
}
