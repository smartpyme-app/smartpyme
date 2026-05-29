<?php

namespace App\Services\FacturacionElectronica\CostaRica;

/**
 * Catálogo nota 23 DGT — NombreInstitucion (FE 4.4).
 */
final class CostaRicaFeNota23Catalog
{
    /** @var array<string, string> */
    private const INSTITUCIONES = [
        '01' => 'Ministerio de Hacienda',
        '02' => 'Ministerio de Relaciones Exteriores y Culto',
        '03' => 'Ministerio de Agricultura y Ganadería',
        '04' => 'Ministerio de Economía, Industria y Comercio',
        '05' => 'Cruz Roja Costarricense',
        '06' => 'Benemérito Cuerpo de Bomberos de Costa Rica',
        '07' => 'Asociación Obras del Espíritu Santo',
        '08' => 'Federación Cruzada Nacional de protección al Anciano (Fecrunapa)',
        '09' => 'Escuela de Agricultura de la Región Húmeda (EARTH)',
        '10' => 'Instituto Centroamericano de Administración de Empresas (INCAE)',
        '11' => 'Junta de Protección Social (JPS)',
        '12' => 'Autoridad Reguladora de los Servicios Públicos (Aresep)',
        '99' => 'Otros',
    ];

    /**
     * @return list<string>
     */
    public static function codigosValidos(): array
    {
        return array_keys(self::INSTITUCIONES);
    }

    public static function esCodigoValido(?string $codigo): bool
    {
        $c = self::formatearCodigo($codigo);

        return $c !== '' && isset(self::INSTITUCIONES[$c]);
    }

    public static function nombre(?string $codigo): ?string
    {
        $c = self::formatearCodigo($codigo);

        return self::INSTITUCIONES[$c] ?? null;
    }

    /**
     * Acepta código nota 23 o nombre legado (p. ej. respuesta API Hacienda) y devuelve el código.
     */
    public static function resolverCodigo(?string $valor): string
    {
        $v = trim((string) $valor);
        if ($v === '') {
            return '';
        }

        $comoCodigo = self::formatearCodigo($v);
        if (isset(self::INSTITUCIONES[$comoCodigo]) && strlen($v) <= 2) {
            return $comoCodigo;
        }

        $nv = self::normalizarTexto($v);
        foreach (self::INSTITUCIONES as $codigo => $nombre) {
            if ($codigo === '99') {
                continue;
            }
            if (self::normalizarTexto($nombre) === $nv) {
                return $codigo;
            }
        }

        foreach (self::INSTITUCIONES as $codigo => $nombre) {
            if ($codigo === '99') {
                continue;
            }
            $nn = self::normalizarTexto($nombre);
            if (str_contains($nv, $nn) || str_contains($nn, $nv)) {
                return $codigo;
            }
        }

        if (isset(self::INSTITUCIONES[$comoCodigo])) {
            return $comoCodigo;
        }

        return '';
    }

    private static function formatearCodigo(?string $codigo): string
    {
        $c = trim((string) $codigo);
        if ($c === '') {
            return '';
        }

        return str_pad($c, 2, '0', STR_PAD_LEFT);
    }

    private static function normalizarTexto(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;

        return preg_replace('/\s+/', ' ', $t) ?? $t;
    }
}
