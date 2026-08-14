<?php

namespace App\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionMovimiento;

class PorVolumenCalculator implements ComisionCalculator
{
    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado
    {
        return null;
    }

    public function calcularEnCierre(object $ctx): array
    {
        $ventas = (float) ($ctx->ventas ?? 0);
        $tramos = (array) ($ctx->regla->config['tramos'] ?? []);
        usort($tramos, fn ($a, $b) => ((float) ($a['umbral'] ?? 0)) <=> ((float) ($b['umbral'] ?? 0)));

        $match = null;
        foreach ($tramos as $tramo) {
            if ($ventas >= (float) ($tramo['umbral'] ?? 0)) {
                $match = $tramo;
            }
        }
        if ($match === null) {
            return [];
        }

        $pct = (float) ($match['porcentaje'] ?? 0);

        return [new ComisionCalculoResultado(
            $ventas,
            $pct,
            round($ventas * ($pct / 100), 4),
            origen: ComisionMovimiento::ORIGEN_AJUSTE_PERIODO,
        )];
    }
}
