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
}
