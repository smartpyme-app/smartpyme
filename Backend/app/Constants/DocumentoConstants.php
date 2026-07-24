<?php

namespace App\Constants;

class DocumentoConstants
{
    public const FACTURA_REMISION = 'Factura de remisión';

    /** Compras: no generan IVA ni deben incluirse en libros fiscales. */
    public const TIPOS_COMPRA_SIN_IVA_FISCAL = [
        self::FACTURA_REMISION,
    ];

    /** Compras/gastos/devoluciones incluidos en libro de compras (El Salvador). */
    public const TIPOS_COMPRA_LIBRO_FISCAL = [
        'Crédito fiscal',
        'Factura',
        'Factura de exportación',
        'Importación',
        'Nota de crédito',
        'Nota de débito',
    ];

    public static function esCompraSinIvaFiscal(?string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_COMPRA_SIN_IVA_FISCAL, true);
    }
}
