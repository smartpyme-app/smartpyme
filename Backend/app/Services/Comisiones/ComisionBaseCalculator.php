<?php

namespace App\Services\Comisiones;

use InvalidArgumentException;

class ComisionBaseCalculator
{
    public function calcular(object $detalle, string $baseCalculo): float
    {
        return match ($baseCalculo) {
            // ponytail: gravada/exenta/no_sujeta en líneas SV ya vienen post-descuento.
            'subtotal_sin_iva' => (float) ($detalle->gravada ?? 0) + (float) ($detalle->exenta ?? 0) + (float) ($detalle->no_sujeta ?? 0),
            'total_con_iva' => (float) ($detalle->total ?? 0),
            'bruto_sin_descuento' => (float) ($detalle->sub_total ?? 0),
            default => throw new InvalidArgumentException("base_calculo desconocida: {$baseCalculo}"),
        };
    }
}
