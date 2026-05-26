<?php

namespace App\Services\Contabilidad\LibrosIva;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/** Determina el módulo de libros IVA según la empresa autenticada. */
class LibroIvaPaisResolver
{
    public const TIPO_SV = 'sv';
    public const TIPO_CR = 'cr';
    public const TIPO_HD = 'hd';
    public const TIPO_GENERAL = 'general';

    public function tipo(?Empresa $empresa = null): string
    {
        $empresa = $empresa ?? Empresa::query()->find(auth()->user()?->id_empresa);

        if (!$empresa) {
            return self::TIPO_GENERAL;
        }

        if (($empresa->pais ?? '') === 'El Salvador') {
            return self::TIPO_SV;
        }

        if (FacturacionElectronicaCountryResolver::codPais($empresa) === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return self::TIPO_CR;
        }

        if (($empresa->pais ?? '') === 'Honduras') {
            return self::TIPO_HD;
        }

        return self::TIPO_GENERAL;
    }
}
