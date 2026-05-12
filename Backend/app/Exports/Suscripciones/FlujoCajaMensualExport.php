<?php

namespace App\Exports\Suscripciones;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FlujoCajaMensualExport implements WithMultipleSheets
{
    /** @var array<int, array<int, string|int|float>> */
    private $filasBloque1;

    /** @var array<int, array<int, string|int|float>> */
    private $filasBloque2;

    public function __construct(array $filasBloque1, array $filasBloque2)
    {
        $this->filasBloque1 = $filasBloque1;
        $this->filasBloque2 = $filasBloque2;
    }

    public function sheets(): array
    {
        return [
            new FlujoCajaMensualBloqueSheet($this->filasBloque1, 'Pagos 1 al 15'),
            new FlujoCajaMensualBloqueSheet($this->filasBloque2, 'Pagos 16 al fin de mes'),
        ];
    }
}
