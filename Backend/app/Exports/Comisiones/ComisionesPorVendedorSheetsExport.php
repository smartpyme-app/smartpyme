<?php

namespace App\Exports\Comisiones;

use App\Services\Comisiones\ComisionReporteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ComisionesPorVendedorSheetsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private ComisionReporteService $reporteService,
        private int $idEmpresa,
        private string $desde,
        private string $hasta
    ) {
    }

    public function sheets(): array
    {
        $agrupados = $this->reporteService->movimientosAgrupadosPorVendedor(
            $this->idEmpresa,
            $this->desde,
            $this->hasta
        );

        if ($agrupados->isEmpty()) {
            return [
                new ComisionVendedorSheet('Sin movimientos', collect()),
            ];
        }

        $sheets = [];

        foreach ($agrupados as $movimientos) {
            /** @var Collection<int, \App\Models\Comisiones\ComisionMovimiento> $movimientos */
            $nombreVendedor = $movimientos->first()?->vendedor?->name ?? 'Vendedor';
            $sheets[] = new ComisionVendedorSheet($nombreVendedor, $movimientos);
        }

        return $sheets;
    }
}
