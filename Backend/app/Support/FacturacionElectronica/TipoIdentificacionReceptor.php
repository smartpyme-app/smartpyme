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

    public static function numeroCostaRica(string $tipo, string $nitDigits, string $duiDigits): ?string
    {
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
        $n = preg_replace('/\D/', '', $numeroSoloDigitos) ?? '';
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

    private static function tieneValor(?string $v): bool
    {
        return $v !== null && trim($v) !== '';
    }
}
