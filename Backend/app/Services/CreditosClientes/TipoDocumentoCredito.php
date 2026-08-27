<?php

namespace App\Services\CreditosClientes;

use InvalidArgumentException;

class TipoDocumentoCredito
{
    public static function documentoBloqueado($idDocumentoContrato): bool
    {
        return $idDocumentoContrato !== null && $idDocumentoContrato !== '';
    }

    public static function puedeFacturar($idVentaCuota): bool
    {
        return empty($idVentaCuota);
    }

    public static function assertCompatible($idDocumentoContrato, $idDocumentoVenta): void
    {
        if (!self::documentoBloqueado($idDocumentoContrato)) {
            return;
        }
        if ((int) $idDocumentoContrato !== (int) $idDocumentoVenta) {
            throw new InvalidArgumentException(
                'El tipo de documento quedó fijo en la primera factura de este crédito.'
            );
        }
    }
}
