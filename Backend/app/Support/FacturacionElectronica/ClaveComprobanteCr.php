<?php

namespace App\Support\FacturacionElectronica;

/**
 * Clave de comprobante CR para QR y nombre de archivo: 50 caracteres alfanuméricos.
 */
final class ClaveComprobanteCr
{
    public static function paraQrYArchivo(string $clave): string
    {
        $normalizada = preg_replace('/[^0-9A-Za-z]/', '', $clave) ?? '';

        return strlen($normalizada) >= 40 ? $normalizada : '';
    }
}
