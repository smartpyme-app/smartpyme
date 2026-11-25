<?php

namespace App\Support\FacturacionElectronica;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Columna de documento en reportes/export de ventas:
 * CR → consecutivo FE (numero_control) o correlativo si no aplica / no hay;
 * otros países → correlativo.
 */
final class IdentificadorDocumentoVentaReporte
{
    public static function esCostaRica(?Empresa $empresa): bool
    {
        return FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa)
            === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA;
    }

    public static function etiqueta(?Empresa $empresa): string
    {
        return self::esCostaRica($empresa) ? 'Consecutivo' : 'Correlativo';
    }

    public static function valor(?Empresa $empresa, ?string $numeroControl, string|int|null $correlativo): string
    {
        if (! self::esCostaRica($empresa)) {
            return (string) ($correlativo ?? '');
        }

        return ClaveComprobanteCr::identificadorParaReporte($numeroControl, $correlativo);
    }
}
