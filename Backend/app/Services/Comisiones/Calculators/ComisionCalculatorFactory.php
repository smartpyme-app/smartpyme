<?php

namespace App\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use InvalidArgumentException;

class ComisionCalculatorFactory
{
    public function __construct(private ComisionPorcentajeResolver $resolver)
    {
    }

    public function for(string $tipo): ComisionCalculator
    {
        return match ($tipo) {
            ComisionRegla::TIPO_POR_CATEGORIA => new PorCategoriaCalculator($this->resolver),
            ComisionRegla::TIPO_POR_MARGEN => new PorMargenCalculator(),
            ComisionRegla::TIPO_POR_VOLUMEN => new PorVolumenCalculator(),
            default => throw new InvalidArgumentException("tipo_calculo desconocido: {$tipo}"),
        };
    }
}
