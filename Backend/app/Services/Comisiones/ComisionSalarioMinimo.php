<?php

namespace App\Services\Comisiones;

class ComisionSalarioMinimo
{
    public static function ajuste(float $comisionMasBase, ?float $minimo): float
    {
        if ($minimo === null) {
            return 0.0;
        }

        return max(0.0, $minimo - $comisionMasBase);
    }

    public static function minimoDePlanilla(?object $config): ?float
    {
        if ($config === null) {
            return null;
        }

        $generales = method_exists($config, 'getConfiguracionesGenerales')
            ? (array) $config->getConfiguracionesGenerales()
            : [];
        $minimo = $generales['salario_minimo'] ?? null;
        if ($minimo === null) {
            $top = is_array($config->configuracion ?? null) ? $config->configuracion : [];
            $minimo = $top['salario_minimo'] ?? null;
        }

        return is_numeric($minimo) ? (float) $minimo : null;
    }
}
