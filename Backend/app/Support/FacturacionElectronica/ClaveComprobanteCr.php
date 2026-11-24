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

    /** Visual: 001-00001-01-0000000145 (20 dígitos). */
    public static function formatoConsecutivo(?string $numeroControl): string
    {
        $digits = preg_replace('/\D/', '', (string) $numeroControl) ?? '';
        if (strlen($digits) >= 20) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 5).'-'.substr($digits, 8, 2).'-'.substr($digits, 10, 10);
        }

        return trim((string) ($numeroControl ?? ''));
    }

    /**
     * Reportes CR: consecutivo FE si hay numero_control; si no, correlativo de la venta.
     */
    public static function identificadorParaReporte(?string $numeroControl, string|int|null $correlativo): string
    {
        $nc = trim((string) ($numeroControl ?? ''));
        if ($nc !== '') {
            return self::formatoConsecutivo($nc);
        }

        return (string) ($correlativo ?? '');
    }
}
