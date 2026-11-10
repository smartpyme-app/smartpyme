<?php

namespace App\Constants;

class ConsignaMovimientoTipoConstants
{
    public const ENTRADA_PROVEEDOR = 'entrada_proveedor';
    public const VENTA_REGULAR_PROPIO = 'venta_regular_propio';
    public const VENTA_REGULAR_CONSIGNA_PROVEEDOR = 'venta_regular_consigna_proveedor';
    public const ENTREGA_CONSIGNA_PROPIO = 'entrega_consigna_propio';
    public const ENTREGA_CONSIGNA_PROVEEDOR = 'entrega_consigna_proveedor';
    public const LIQUIDACION_PROVEEDOR = 'liquidacion_proveedor';
    public const LIQUIDACION_CLIENTE = 'liquidacion_cliente';
    public const DEVOLUCION_PROVEEDOR = 'devolucion_proveedor';
    public const DEVOLUCION_CLIENTE = 'devolucion_cliente';
    public const TRASLADO_CONSIGNA = 'traslado_consigna';
    public const AJUSTE_CONSIGNA = 'ajuste_consigna';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::ENTRADA_PROVEEDOR,
            self::VENTA_REGULAR_PROPIO,
            self::VENTA_REGULAR_CONSIGNA_PROVEEDOR,
            self::ENTREGA_CONSIGNA_PROPIO,
            self::ENTREGA_CONSIGNA_PROVEEDOR,
            self::LIQUIDACION_PROVEEDOR,
            self::LIQUIDACION_CLIENTE,
            self::DEVOLUCION_PROVEEDOR,
            self::DEVOLUCION_CLIENTE,
            self::TRASLADO_CONSIGNA,
            self::AJUSTE_CONSIGNA,
        ];
    }

    public static function esValido(?string $tipo): bool
    {
        if ($tipo === null || $tipo === '') {
            return false;
        }

        return in_array($tipo, self::todos(), true);
    }
}
