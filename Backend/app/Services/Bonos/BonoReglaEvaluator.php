<?php

namespace App\Services\Bonos;

use InvalidArgumentException;

class BonoReglaEvaluator
{
    public function calcular(string $tipo, array $config, float $ventas): float
    {
        return match ($tipo) {
            'meta_fija' => $ventas >= (float) $config['meta'] ? (float) $config['bono'] : 0.0,
            'escalonado' => $this->escalonado($config['tramos'] ?? [], $ventas),
            default => throw new InvalidArgumentException("tipo bono desconocido: {$tipo}"),
        };
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
