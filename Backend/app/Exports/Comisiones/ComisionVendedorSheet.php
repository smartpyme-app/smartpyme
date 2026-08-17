<?php

namespace App\Exports\Comisiones;

use App\Models\Comisiones\ComisionMovimiento;
use App\Services\Comisiones\ComisionReporteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComisionVendedorSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private string $nombreVendedor,
        private Collection $movimientos
    ) {
    }

    public function collection(): Collection
    {
        $filas = $this->movimientos->map(function (ComisionMovimiento $movimiento) {
            return [
                'correlativo' => $movimiento->venta?->correlativo ?? '',
                'fecha' => $movimiento->fecha_evento?->format('Y-m-d') ?? '',
                'categoria' => $movimiento->categoria?->nombre ?? '',
                'origen' => ComisionReporteService::etiquetaOrigen($movimiento->origen),
                'monto_base' => (float) $movimiento->monto_base,
                'porcentaje' => (float) $movimiento->porcentaje_aplicado,
                'comision' => (float) $movimiento->monto_comision,
                'es_total' => false,
            ];
        });

        $filas->push([
            'correlativo' => '',
            'fecha' => '',
            'categoria' => '',
            'origen' => 'TOTAL',
            'monto_base' => '',
            'porcentaje' => '',
            'comision' => (float) $this->movimientos->sum('monto_comision'),
            'es_total' => true,
        ]);

        return $filas;
    }

    public function title(): string
    {
        return substr($this->nombreVendedor, 0, 31);
    }

    public function headings(): array
    {
        return [
            'Correlativo',
            'Fecha',
            'Categoría',
            'Origen',
            'Monto base',
            '%',
            'Comisión',
        ];
    }

    public function map($fila): array
    {
        if ($fila['es_total']) {
            return [
                '',
                '',
                '',
                'TOTAL',
                '',
                '',
                number_format($fila['comision'], 2),
            ];
        }

        return [
            $fila['correlativo'],
            $fila['fecha'],
            $fila['categoria'],
            $fila['origen'],
            number_format($fila['monto_base'], 2),
            number_format($fila['porcentaje'], 2),
            number_format($fila['comision'], 2),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $totalRow = $this->movimientos->count() + 2;

        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
            $totalRow => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E0F7FA']]],
        ];
    }
}
