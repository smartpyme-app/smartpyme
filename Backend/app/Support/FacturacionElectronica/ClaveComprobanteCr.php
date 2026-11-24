<?php

namespace App\Support\FacturacionElectronica;

/**
 * Clave de comprobante CR para QR y nombre de archivo: 50 caracteres alfanuméricos.
 */
final class ClaveComprobanteCr
{
    /** Quita espacios/guiones y deja solo [0-9A-Za-z]. */
    public static function normalizar(string $clave): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', trim($clave)) ?? '';
    }

    public static function paraQrYArchivo(string $clave): string
    {
        $normalizada = self::normalizar($clave);

        return strlen($normalizada) >= 40 ? $normalizada : '';
    }

    /**
     * Clave DGT (50): posiciones 21..40 = NumeroConsecutivo (20).
     * Normaliza antes de medir longitud (evita numero_control null con clave “sucia”).
     */
    public static function consecutivoDesdeClave(string $clave): ?string
    {
        $normalizada = self::normalizar($clave);

        return strlen($normalizada) === 50 ? substr($normalizada, 21, 20) : null;
    }
}
