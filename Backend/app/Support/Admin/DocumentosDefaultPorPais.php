<?php

namespace App\Support\Admin;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Nombres de documentos al crear empresa/sucursal (alineado con Frontend documento-nombre-options.ts).
 */
final class DocumentosDefaultPorPais
{
    public const CR_TIQUETE = 'Tiquete Electrónico';

    public const CR_FACTURA = 'Factura Electrónica';

    /** @return list<string> */
    public static function nombres(?Empresa $empresa): array
    {
        if (FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa) === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return [
                self::CR_TIQUETE,
                self::CR_FACTURA,
                config('constants.TIPO_DOCUMENTO_COTIZACION', 'Cotización'),
                config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA', 'Orden de compra'),
            ];
        }

        return [
            config('constants.TIPO_DOCUMENTO_TICKET', 'Ticket'),
            config('constants.TIPO_DOCUMENTO_FACTURA', 'Factura'),
            config('constants.TIPO_DOCUMENTO_CREDITO_FISCAL', 'Crédito fiscal'),
            config('constants.TIPO_DOCUMENTO_COTIZACION', 'Cotización'),
            config('constants.TIPO_DOCUMENTO_ORDEN_COMPRA', 'Orden de compra'),
        ];
    }
}
