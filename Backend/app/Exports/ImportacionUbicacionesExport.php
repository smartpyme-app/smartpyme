<?php

namespace App\Exports;

use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportacionUbicacionesExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    public function __construct()
    {
        $idEmpresa = Auth::user()?->id_empresa;
        if (!$idEmpresa) {
            return;
        }

        $productos = Producto::query()
            ->where('id_empresa', $idEmpresa)
            ->where('tipo', '!=', 'Servicio')
            ->with(['inventarios.bodega'])
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        foreach ($productos as $producto) {
            $filas = 0;
            foreach ($producto->inventarios as $inventario) {
                $bodega = $inventario->bodega;
                if (!$bodega || (string) $bodega->activo === '0') {
                    continue;
                }
                $this->rows[] = [$producto->codigo, $producto->nombre, $bodega->nombre, '', '', ''];
                $filas++;
            }
            if ($filas === 0) {
                $this->rows[] = [$producto->codigo, $producto->nombre, '', '', '', ''];
            }
        }
    }

    public function headings(): array
    {
        return ['codigo', 'nombre', 'bodega', 'ubicacion', 'ubicacion_especifica', 'detalle'];
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Ubicaciones';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2EFDA'],
                ],
            ],
        ];
    }
}
