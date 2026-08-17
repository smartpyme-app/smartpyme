<?php

namespace App\Services\Bonos\Calculators;

class MetaFijaCalculator implements BonoCalculator
{
    public function calcular(array $config, float $ventas): float
    {
        return $ventas >= (float) $config['meta'] ? (float) $config['bono'] : 0.0;
    }
}
