<?php

namespace App\Exports;

use App\Services\RecuperarVentasPerdidasService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class VentasPerdidasExport implements WithMultipleSheets
{
    protected $datos;

    public function __construct(string $fechaInicio, string $fechaFin, ?int $idEmpresa = null)
    {
        $service = new RecuperarVentasPerdidasService($fechaInicio, $fechaFin, $idEmpresa);
        $this->datos = $service->getDatosCompletos();
    }

    public function sheets(): array
    {
        return [
            new VentasPerdidasSheet($this->datos['ventas_por_cliente']),
            new ClientesPerdidosSheet($this->datos['clientes_perdidos']),
        ];
    }

    public function getDatos()
    {
        return $this->datos;
    }
}
