<?php

namespace App\Services\Bonos;

use App\Services\Bonos\Calculators\BonoCalculatorFactory;

class BonoReglaEvaluator
{
    public function calcular(string $tipo, array $config, float $ventas): float
    {
        return (new BonoCalculatorFactory())->for($tipo)->calcular($config, $ventas);
    }
}
