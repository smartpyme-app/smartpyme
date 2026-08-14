<?php

namespace App\Services\Bonos\Calculators;

class CualitativoManualCalculator implements BonoCalculator
{
    public function calcular(array $config, float $ventas): float
    {
        return 0.0;
    }
}
