<?php

namespace App\Services\Comisiones\Calculators;

class PorMargenCalculator implements ComisionCalculator
{
    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado
    {
        $pct = (float) (($ctx->regla->config['porcentaje'] ?? 0));
        if ($pct == 0.0) {
            return null;
        }

        $detalle = $ctx->detalle ?? (object) [];
        $producto = $detalle->producto ?? null;
        $cantidad = (float) ($detalle->cantidad ?? 0);
        $costoPromedio = (float) ($producto->costo_promedio ?? 0);
        $costo = (float) ($producto->costo ?? 0);
        $costoLinea = $cantidad * ($costoPromedio > 0 ? $costoPromedio : $costo);
        $baseMargen = max(0.0, (float) $ctx->base - $costoLinea);
        if ($baseMargen <= 0) {
            return null;
        }

        return new ComisionCalculoResultado(
            $baseMargen,
            $pct,
            round($baseMargen * ($pct / 100), 4),
            isset($ctx->id_categoria) ? (int) $ctx->id_categoria : null,
            isset($ctx->id_subcategoria) ? (int) $ctx->id_subcategoria : null,
        );
    }

    public function calcularEnCierre(object $ctx): array
    {
        return [];
    }
}
