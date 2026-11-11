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

    /** Tipos del kardex consigna compra (pool proveedor); excluye entrega stock propio (custodia clientes). */
    public static function tiposKardexPoolProveedor(): array
    {
        return [
            self::ENTRADA_PROVEEDOR,
            self::VENTA_REGULAR_CONSIGNA_PROVEEDOR,
            self::ENTREGA_CONSIGNA_PROVEEDOR,
            self::LIQUIDACION_PROVEEDOR,
            self::DEVOLUCION_PROVEEDOR,
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

    public static function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::ENTRADA_PROVEEDOR => 'Entrada consigna (proveedor)',
            self::VENTA_REGULAR_PROPIO => 'Venta (stock propio)',
            self::VENTA_REGULAR_CONSIGNA_PROVEEDOR => 'Venta (stock consigna compra)',
            self::ENTREGA_CONSIGNA_PROPIO => 'Entrega a cliente (stock propio)',
            self::ENTREGA_CONSIGNA_PROVEEDOR => 'Entrega a cliente (consigna compra)',
            self::LIQUIDACION_PROVEEDOR => 'Liquidación al proveedor',
            self::LIQUIDACION_CLIENTE => 'Liquidación al cliente',
            self::DEVOLUCION_PROVEEDOR => 'Devolución al proveedor',
            self::DEVOLUCION_CLIENTE => 'Devolución del cliente',
            self::TRASLADO_CONSIGNA => 'Traslado pool consigna',
            self::AJUSTE_CONSIGNA => 'Ajuste / conteo consigna',
            default => $tipo,
        };
    }

    public static function ayuda(string $tipo): string
    {
        return match ($tipo) {
            self::ENTRADA_PROVEEDOR => 'Recepción en consigna; el inventario físico sube; la propiedad del proveedor se registra aquí.',
            self::VENTA_REGULAR_CONSIGNA_PROVEEDOR => 'Venta facturada que descontó unidades del pool de consigna del proveedor (no del stock propio).',
            self::LIQUIDACION_PROVEEDOR => 'Pago al proveedor por unidades ya vendidas; no reduce de nuevo el pool ni las existencias del kardex.',
            self::ENTREGA_CONSIGNA_PROPIO, self::ENTREGA_CONSIGNA_PROVEEDOR => 'Remisión / venta en consigna: mercadería entregada al cliente pendiente de facturar.',
            self::LIQUIDACION_CLIENTE => 'Facturación del consumo del cliente en consigna salida.',
            default => '',
        };
    }
}
