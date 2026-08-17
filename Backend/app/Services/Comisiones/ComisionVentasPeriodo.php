<?php

namespace App\Services\Comisiones;

use App\Services\Bonos\BonoMetaCalculator;

class ComisionVentasPeriodo
{
    public function __construct(private ?BonoMetaCalculator $meta = null)
    {
        $this->meta ??= new BonoMetaCalculator();
    }

    public function total(int $idEmpresa, int $idVendedor, string $inicio, string $fin): float
    {
        return $this->meta->ventasVendedorPeriodo($idEmpresa, $idVendedor, $inicio, $fin);
    }
}
