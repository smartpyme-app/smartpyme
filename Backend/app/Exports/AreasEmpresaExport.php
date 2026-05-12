<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AreasEmpresaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $areas;

    public function __construct($areas)
    {
        $this->areas = $areas;
    }

    public function collection()
    {
        return $this->areas;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Descripción',
            'Sucursal',
            'Departamento',
            'Estado',
            'Fecha de Creación',
            'Fecha de Actualización',
        ];
    }

    public function map($area): array
    {
        $sucursalNombre = 'N/A';
        if ($area->departamento && $area->departamento->sucursal) {
            $sucursalNombre = $area->departamento->sucursal->nombre;
        }

        return [
            $area->id,
            $area->nombre,
            $area->descripcion ?: 'N/A',
            $sucursalNombre,
            $area->departamento ? $area->departamento->nombre : 'N/A',
            $area->activo ? 'Activo' : 'Inactivo',
            $area->created_at ? $area->created_at->format('d/m/Y H:i') : 'N/A',
            $area->updated_at ? $area->updated_at->format('d/m/Y H:i') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
