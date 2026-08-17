<?php

namespace App\Services\Comisiones\Calculators;

class ComisionCalculoResultado
{
    public function __construct(
        public float $montoBase,
        public float $porcentaje,
        public float $montoComision,
        public ?int $idCategoria = null,
        public ?int $idSubcategoria = null,
        public string $origen = 'venta',
    ) {
    }
}
