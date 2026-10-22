<?php

namespace App\Support\FacturacionElectronica;

/**
 * Tipo de identificación del receptor para FE.
 * Si el cliente/proveedor no tiene tipo guardado, se infiere igual que antes.
 */
final class TipoIdentificacionReceptor
{
    public const CODIGOS_CR = ['01', '02', '03', '04', '05', '06'];

    public static function tipoGuardado(?string $tipo): ?string
    {
        $t = trim((string) $tipo);

        return $t === '' ? null : $t;
    }

    /**
     * Inferencia histórica CR: nit≥9 → 02, dui≥9 → 01, si no → null (receptor genérico).
     */
    public static function costaRica(?string $tipoDocumento, string $nitDigits, string $duiDigits): ?string
    {
        $guardado = self::tipoGuardado($tipoDocumento);
        if ($guardado !== null && in_array($guardado, self::CODIGOS_CR, true)) {
            return $guardado;
        }
        if (self::cedulaJuridicaConLetra($nitDigits) || self::cedulaJuridicaConLetra($duiDigits)) {
            return '02';
        }
        $nitDigits = preg_replace('/\D/', '', $nitDigits) ?? '';
        $duiDigits = preg_replace('/\D/', '', $duiDigits) ?? '';
        if (strlen($nitDigits) >= 9) {
            return '02';
        }
        if (strlen($duiDigits) >= 9) {
            return '01';
        }

        return null;
    }

    /**
     * Inferencia histórica SV (MH.php): si hay nit → 36; si hay dui → 13 (dui gana).
     */
    public static function elSalvador(?string $tipoDocumento, ?string $nit, ?string $dui): ?string
    {
        $guardado = self::tipoGuardado($tipoDocumento);
        if ($guardado !== null) {
            return $guardado;
        }
        $tipo = null;
        if (self::tieneValor($nit)) {
            $tipo = '36';
        }
        if (self::tieneValor($dui)) {
            $tipo = '13';
        }

        return $tipo;
    }

    /**
     * Tipo 02 conserva letras (cédula jurídica). El resto queda solo dígitos.
     */
    public static function normalizarNumeroCostaRica(string $tipo, string $raw): string
    {
        if ($tipo === '02') {
            return preg_replace('/[^0-9A-Za-z]/', '', $raw) ?? '';
        }

        return preg_replace('/\D/', '', $raw) ?? '';
    }

    public static function numeroCostaRica(string $tipo, string $nitDigits, string $duiDigits): ?string
    {
        if ($tipo === '02') {
            $nitDigits = self::normalizarNumeroCostaRica('02', $nitDigits);
            $duiDigits = self::normalizarNumeroCostaRica('02', $duiDigits);
        }

        if (in_array($tipo, ['02', '04'], true) && strlen($nitDigits) >= 9) {
            return $nitDigits;
        }
        if ($tipo === '01' && strlen($duiDigits) >= 9) {
            return substr(str_pad($duiDigits, 9, '0', STR_PAD_LEFT), 0, 9);
        }
        if (strlen($nitDigits) >= 9) {
            return $nitDigits;
        }
        if (strlen($duiDigits) >= 9) {
            return $duiDigits;
        }

        return null;
    }

    /**
     * FE (01): tipos 05/06 no son consumidor final; Hacienda rechaza -17/-38 con placeholders genéricos.
     */
    public static function estructuraValidaCostaRicaFactura(string $tipo, string $numeroSoloDigitos): bool
    {
        $n = self::normalizarNumeroCostaRica($tipo, $numeroSoloDigitos);
        if ($n === '' || preg_match('/^0+$/', $n) === 1) {
            return false;
        }

        return match ($tipo) {
            '01' => strlen($n) === 9,
            '02' => strlen($n) === 10,
            '03' => strlen($n) === 11 || strlen($n) === 12,
            '04' => strlen($n) === 10,
            default => false,
        };
    }

    public static function numeroElSalvador(string $tipo, ?string $nit, ?string $dui): ?string
    {
        $nitClean = self::tieneValor($nit) ? str_replace('-', '', (string) $nit) : null;
        $duiVal = self::tieneValor($dui) ? (string) $dui : null;

        if ($tipo === '36') {
            return $nitClean ?: $duiVal;
        }

        return $duiVal ?: $nitClean;
    }

    /**
     * 10 alfanuméricos con al menos una letra, ya sin espacios ni guiones. Va antes del conteo de dígitos.
     */
    private static function cedulaJuridicaConLetra(string $raw): bool
    {
        $n = preg_replace('/[\s-]/', '', $raw) ?? '';

        return strlen($n) === 10
            && preg_match('/^[0-9A-Za-z]{10}$/', $n) === 1
            && preg_match('/[A-Za-z]/', $n) === 1;
    }

    private static function tieneValor(?string $v): bool
    {
        return $v !== null && trim($v) !== '';
    }
}
