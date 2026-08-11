<?php

namespace App\Exports\Suscripciones;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BajasMensualesExport implements WithMultipleSheets
{
    /** @var array<int, array<int, string|int|float>> */
    private $filasDetalle;

    /** @var array<int, array<int, string|int|float>> */
    private $filasHistorico;

    /** @var array<int, array<int, string|int|float>> */
    private $filasProyeccion;

    public function __construct(array $filasDetalle, array $filasHistorico, array $filasProyeccion)
    {
        $this->filasDetalle = $filasDetalle;
        $this->filasHistorico = $filasHistorico;
        $this->filasProyeccion = $filasProyeccion;
    }

    public function sheets(): array
    {
        return [
            new FlujoCajaMensualBloqueSheet($this->filasDetalle, 'Bajas del mes'),
            new FlujoCajaMensualBloqueSheet($this->filasHistorico, 'Historico 12m'),
            new FlujoCajaMensualBloqueSheet($this->filasProyeccion, 'Proyeccion'),
        ];
    }
}
