<?php

namespace App\Services\Bonos\Calculators;

class PorcentajeExcedenteCalculator implements BonoCalculator
{
    public function calcular(array $config, float $ventas): float
    {
        $meta = (float) ($config['meta'] ?? 0);
        $pct = (float) ($config['porcentaje'] ?? 0);
        if ($ventas <= $meta) {
            return 0.0;
        }

        return round(($ventas - $meta) * ($pct / 100), 4);
    }
}
