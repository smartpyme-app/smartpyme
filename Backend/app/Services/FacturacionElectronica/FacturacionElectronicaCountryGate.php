<?php

namespace App\Services\FacturacionElectronica;

use App\Models\Admin\Empresa;
use Illuminate\Http\JsonResponse;

/**
 * Punto único para validar si la empresa puede usar el flujo DTE actual (Ministerio de Hacienda SV).
 * Costa Rica u otros países: 501 hasta tener implementación.
 */
final class FacturacionElectronicaCountryGate
{
    public static function ensureSvDteOrFail(?Empresa $empresa): ?JsonResponse
    {
        $cod = FacturacionElectronicaCountryResolver::codPais($empresa);

        if ($cod === FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR) {
            return null;
        }

        return response()->json([
            'error' => 'La facturación electrónica para este país aún no está disponible en el sistema.',
            'cod_pais' => $cod,
        ], 501);
    }
}
