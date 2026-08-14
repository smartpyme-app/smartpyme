<?php

namespace App\Services\Comisiones\Calculators;

use App\Services\Comisiones\ComisionPorcentajeResolver;

class PorCategoriaCalculator implements ComisionCalculator
{
    public function __construct(private ComisionPorcentajeResolver $resolver)
    {
    }

    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado
    {
        $pct = $this->resolver->resolver(
            (int) $ctx->id_empresa,
            isset($ctx->id_categoria) ? (int) $ctx->id_categoria : null,
            isset($ctx->id_subcategoria) ? (int) $ctx->id_subcategoria : null
        );
        if ($pct == 0.0) {
            return null;
        }
        $base = (float) $ctx->base;
        if ($base <= 0) {
            return null;
        }

        return new ComisionCalculoResultado(
            $base,
            $pct,
            round($base * ($pct / 100), 4),
            isset($ctx->id_categoria) ? (int) $ctx->id_categoria : null,
            isset($ctx->id_subcategoria) ? (int) $ctx->id_subcategoria : null,
        );
    }

    public function calcularEnCierre(object $ctx): array
    {
        return [];
    }
}
