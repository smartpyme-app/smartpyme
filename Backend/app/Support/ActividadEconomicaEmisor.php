<?php

namespace App\Support;

/**
 * Giro del emisor: sucursal si tiene código o texto; si no, empresa.
 */
final class ActividadEconomicaEmisor
{
    /**
     * @return array{cod: mixed, giro: mixed}
     */
    public static function resolver(?object $empresa, ?object $sucursal): array
    {
        $codEmpresa = $empresa->cod_actividad_economica ?? null;
        $giroEmpresa = $empresa->giro ?? null;

        $codSuc = is_object($sucursal) ? trim((string) ($sucursal->cod_actividad_economica ?? '')) : '';
        $giroSuc = is_object($sucursal) ? trim((string) ($sucursal->giro ?? '')) : '';

        if ($codSuc === '' && $giroSuc === '') {
            return ['cod' => $codEmpresa, 'giro' => $giroEmpresa];
        }

        return [
            'cod' => $codSuc !== '' ? $sucursal->cod_actividad_economica : $codEmpresa,
            'giro' => $giroSuc !== '' ? $sucursal->giro : $giroEmpresa,
        ];
    }
}
