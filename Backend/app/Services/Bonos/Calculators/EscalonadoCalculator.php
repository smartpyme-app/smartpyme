<?php

namespace App\Services\Bonos\Calculators;

class EscalonadoCalculator implements BonoCalculator
{
    public function calcular(array $config, float $ventas): float
    {
        return $this->escalonado($config['tramos'] ?? [], $ventas);
    }

    /**
     * @param  array<int, array{meta?: float|int, bono?: float|int}>  $tramos
     */
    private function escalonado(array $tramos, float $ventas): float
    {
        $bono = 0.0;
        foreach ($tramos as $tramo) {
            if ($ventas >= (float) ($tramo['meta'] ?? 0)) {
                $bono = (float) ($tramo['bono'] ?? 0);
            }
        }

        return $bono;
    }
}
