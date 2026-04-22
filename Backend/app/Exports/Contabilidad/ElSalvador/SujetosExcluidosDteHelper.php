<?php

namespace App\Exports\Contabilidad\ElSalvador;

/**
 * Extrae sello y código de generación del DTE (tipo 14) almacenado en compra/gasto.
 * El código de generación se entrega con el formato original (incluye guiones).
 */
final class SujetosExcluidosDteHelper
{
    public static function dteArray($registro): ?array
    {
        $dte = $registro->dte ?? null;
        if ($dte === null) {
            return null;
        }
        if (is_string($dte)) {
            $decoded = json_decode($dte, true);
            return is_array($decoded) ? $decoded : null;
        }

        return is_array($dte) ? $dte : null;
    }

    public static function codigoGeneracion($registro): string
    {
        $dte = self::dteArray($registro);
        $raw = data_get($dte, 'identificacion.codigoGeneracion', '');
        if ($raw === null || $raw === '') {
            return '';
        }

        return strtoupper((string) $raw);
    }

    public static function selloRecepcion($registro): string
    {
        if (!empty($registro->sello_mh)) {
            return (string) $registro->sello_mh;
        }

        return '';
    }
}
