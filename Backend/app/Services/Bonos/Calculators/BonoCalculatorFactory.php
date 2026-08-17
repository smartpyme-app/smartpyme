<?php

namespace App\Services\Bonos\Calculators;

use InvalidArgumentException;

class BonoCalculatorFactory
{
    public function for(string $tipo): BonoCalculator
    {
        return match ($tipo) {
            'meta_fija' => new MetaFijaCalculator(),
            'escalonado' => new EscalonadoCalculator(),
            'porcentaje_excedente' => new PorcentajeExcedenteCalculator(),
            'grupal' => new GrupalCalculator(),
            'cualitativo_manual' => new CualitativoManualCalculator(),
            default => throw new InvalidArgumentException("tipo bono desconocido: {$tipo}"),
        };
    }
}
