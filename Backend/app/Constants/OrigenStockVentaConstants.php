<?php

namespace App\Constants;

class OrigenStockVentaConstants
{
    public const CONSIGNA_COMPRA = 'consigna_compra';
    public const NORMAL = 'normal';

    public static function esConsignaCompra(?string $origen): bool
    {
        return $origen === self::CONSIGNA_COMPRA;
    }
}
