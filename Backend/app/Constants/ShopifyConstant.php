<?php

namespace App\Constants;

class ShopifyConstant
{
    const MAPEO_DEPARTAMENTOS = [
        'SV-AH' => '01', // Ahuachapán
        'SV-SA' => '02', // Santa Ana
        'SV-SO' => '03', // Sonsonate
        'SV-CH' => '04', // Chalatenango
        'SV-LI' => '05', // La Libertad
        'SV-SS' => '06', // San Salvador
        'SV-CU' => '07', // Cuscatlán
        'SV-PA' => '08', // La Paz
        'SV-CA' => '09', // Cabañas
        'SV-SV' => '10', // San Vicente
        'SV-US' => '11', // Usulután
        'SV-SM' => '12', // San Miguel
        'SV-MO' => '13', // Morazán
        'SV-UN' => '14', // La Unión
    ];

    const MAPEO_DEPARTAMENTOS_NOMBRES = [
        '01' => 'Ahuachapán',
        '02' => 'Santa Ana',
        '03' => 'Sonsonate',
        '04' => 'Chalatenango',
        '05' => 'La Libertad',
        '06' => 'San Salvador',
        '07' => 'Cuscatlán',
        '08' => 'La Paz',
        '09' => 'Cabañas',
        '10' => 'San Vicente',
        '11' => 'Usulután',
        '12' => 'San Miguel',
        '13' => 'Morazán',
        '14' => 'La Unión',
    ];

    public static function obtenerCodigoDepartamento($provinceCode)
    {
        if (empty($provinceCode)) {
            return null;
        }

        $provinceCodeUpper = strtoupper(trim($provinceCode));

        if (isset(self::MAPEO_DEPARTAMENTOS[$provinceCodeUpper])) {
            return self::MAPEO_DEPARTAMENTOS[$provinceCodeUpper];
        }

        foreach (self::MAPEO_DEPARTAMENTOS as $codigoShopify => $codigoNumerico) {
            if (strpos($provinceCodeUpper, $codigoShopify) !== false) {
                return $codigoNumerico;
            }
        }

        return null;
    }
}

