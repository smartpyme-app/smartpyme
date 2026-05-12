<?php

namespace App\Exports;

use App\Models\Inventario\Bodega;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlantillaProductosImportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /** @var array<int, string> */
    private array $headingsRow;

    /** @var array<int, array<int, string>> */
    private array $rows;

    public function __construct()
    {
        $user = Auth::user();
        $base = [
            'nombre',
            'precio_sin_iva',
            'costo',
            'categoria',
            'codigo',
            'descripcion',
            'marca',
            'unidad_medida',
            'codigo_de_barra',
            'proveedor_nombre',
            'proveedor_apellido',
        ];

        $bodegas = Bodega::where('id_empresa', $user->id_empresa)
            ->where('activo', true)
            ->with('sucursal')
            ->orderBy('id_sucursal')
            ->orderBy('id')
            ->get();

        foreach ($bodegas as $bodega) {
            $nomBodega = $this->sanitizeForExcelHeader($bodega->nombre ?? '');
            $nomSucursal = $bodega->sucursal
                ? $this->sanitizeForExcelHeader($bodega->sucursal->nombre)
                : 'Sin sucursal';
            $base[] = 'stock_' . $bodega->id . ' - ' . $nomBodega . ' (' . $nomSucursal . ')';
        }

        $this->headingsRow = $base;
        $this->rows = [
            array_fill(0, count($this->headingsRow), ''),
        ];
    }

    private function sanitizeForExcelHeader(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text !== '' ? $text : '-';
    }

    public function headings(): array
    {
        return $this->headingsRow;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Importar productos';
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
