<?php

namespace App\Services\Comisiones\Calculators;

interface ComisionCalculator
{
    public function calcularEnEvento(object $ctx): ?ComisionCalculoResultado;

    /** @return list<ComisionCalculoResultado> */
    public function calcularEnCierre(object $ctx): array;
}
