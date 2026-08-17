<?php

namespace App\Services\Bonos\Calculators;

interface BonoCalculator
{
    public function calcular(array $config, float $ventas): float;
}
