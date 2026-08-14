<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;

class ComisionVendedoresCierre
{
    /**
     * @param  list<int>  $idsMovimientos
     * @param  list<object>  $reglas
     * @param  list<int>  $idsVendedoresEmpresa
     * @return list<int>
     */
    public static function unir(array $idsMovimientos, array $reglas, array $idsVendedoresEmpresa): array
    {
        $ids = $idsMovimientos;
        $hayGlobalPeriodo = false;
        foreach ($reglas as $regla) {
            if (! self::esReglaPeriodoOBase($regla)) {
                continue;
            }
            $alcance = (string) ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
            if ($alcance === ComisionRegla::ALCANCE_GLOBAL) {
                $hayGlobalPeriodo = true;
                continue;
            }
            $ids = array_merge($ids, array_map('intval', (array) ($regla->id_vendedores ?? [])));
        }
        if ($hayGlobalPeriodo) {
            $ids = array_merge($ids, $idsVendedoresEmpresa);
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => (int) $id > 0)));
    }

    public static function esReglaPeriodoOBase(object $regla): bool
    {
        if (($regla->tipo_calculo ?? '') === ComisionRegla::TIPO_POR_VOLUMEN) {
            return true;
        }

        return (float) ($regla->config['salario_base'] ?? 0) > 0;
    }
}
