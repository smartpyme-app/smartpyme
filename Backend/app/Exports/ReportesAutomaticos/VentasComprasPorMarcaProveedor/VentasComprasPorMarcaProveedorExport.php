<?php

namespace App\Exports\ReportesAutomaticos\VentasComprasPorMarcaProveedor;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class VentasComprasPorMarcaProveedorExport implements WithMultipleSheets
{
    use Exportable;

    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $sucursales;
    public $configuracion;

    public function __construct($fechaInicio, $fechaFin, $id_empresa, $configuracion, $sucursales = [])
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->configuracion = $configuracion;
        $this->sucursales = $sucursales;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new VentasComprasPorMarcaProveedorSheet1(
                $this->fechaInicio,
                $this->fechaFin,
                $this->id_empresa,
                $this->sucursales
            ),
            new VentasComprasPorMarcaProveedorSheet2(
                $this->fechaInicio,
                $this->fechaFin,
                $this->id_empresa,
                $this->sucursales
            ),
        ];
    }
}
