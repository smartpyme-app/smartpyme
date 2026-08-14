<?php

namespace App\Services\Comisiones;

class ComisionSalarioMinimo
{
    public static function ajuste(float $comisionMasBase, ?float $minimo): float
    {
        if ($minimo === null) {
            return 0.0;
        }

        return max(0.0, $minimo - $comisionMasBase);
    }
}
