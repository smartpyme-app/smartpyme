<?php

namespace App\Services\FacturacionElectronica;

use App\Models\Admin\Empresa;

/**
 * Resuelve el código ISO de país para FE. Mantiene SV por defecto (retrocompatibilidad).
 */
final class FacturacionElectronicaCountryResolver
{
    public const CODIGO_EL_SALVADOR = 'SV';

    public const CODIGO_COSTA_RICA = 'CR';

    public static function codPais(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return self::CODIGO_EL_SALVADOR;
        }

        $cod = $empresa->cod_pais;
        if ($cod !== null && $cod !== '') {
            return strtoupper((string) $cod);
        }

        $nombre = strtolower(trim((string) ($empresa->pais ?? '')));

        if (str_contains($nombre, 'costa rica')) {
            return self::CODIGO_COSTA_RICA;
        }

        if (str_contains($nombre, 'salvador')) {
            return self::CODIGO_EL_SALVADOR;
        }

        return self::CODIGO_EL_SALVADOR;
    }

    /** Alineado con Frontend fe-pais.util.ts (`pais` manda sobre `cod_pais`). */
    public static function resolveCodigoPaisFe(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return self::CODIGO_EL_SALVADOR;
        }

        $fromNombre = self::codigoFromNombrePais($empresa->pais);
        if ($fromNombre !== null) {
            return $fromNombre;
        }

        $cod = strtoupper(trim((string) ($empresa->cod_pais ?? '')));
        if ($cod !== '' && in_array($cod, ['SV', 'CR', 'GT', 'HN'], true)) {
            return $cod;
        }

        return self::CODIGO_EL_SALVADOR;
    }

    public static function esElSalvadorFe(?Empresa $empresa): bool
    {
        return self::resolveCodigoPaisFe($empresa) === self::CODIGO_EL_SALVADOR;
    }

    private static function codigoFromNombrePais(?string $pais): ?string
    {
        $nombre = strtolower(trim((string) ($pais ?? '')));
        if ($nombre === '') {
            return null;
        }
        if (str_contains($nombre, 'costa rica')) {
            return self::CODIGO_COSTA_RICA;
        }
        if (str_contains($nombre, 'salvador')) {
            return self::CODIGO_EL_SALVADOR;
        }
        if (str_contains($nombre, 'guatemala')) {
            return 'GT';
        }
        if (str_contains($nombre, 'honduras')) {
            return 'HN';
        }
        $upper = strtoupper($nombre);
        if (in_array($upper, ['SV', 'CR', 'GT', 'HN'], true)) {
            return $upper;
        }

        return null;
    }
}
