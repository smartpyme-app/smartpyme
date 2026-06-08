<?php

namespace App\Exports\ReportesAutomaticos\VentasPorCategoriaPorSucursal;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VentasPorCategoriaSucursalMultiExport implements WithMultipleSheets
{
    use Exportable;

    public $fechaInicio;
    public $fechaFin;
    public $empresas;

    /**
     * @param  array<int, array{id: int, nombre: string, configuracion: object}>  $empresas
     */
    public function __construct(string $fechaInicio, string $fechaFin, array $empresas)
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->empresas = $empresas;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->empresas as $empresa) {
            $sheets[] = new VentasPorCategoriaSucursalExport(
                $this->fechaInicio,
                $this->fechaFin,
                $empresa['id'],
                $empresa['configuracion'],
                $empresa['nombre']
            );
        }

        return $sheets;
    }
}
