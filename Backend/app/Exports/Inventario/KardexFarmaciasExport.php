<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Http\Request;
use App\Models\Inventario\Kardex;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KardexFarmaciasExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Medicamento / Producto',
            'Bodega',
            'Lote',
            'Tipo de movimiento',
            'N° Documento',
            'Entrada',
            'Salida',
            'Stock',
            'Costo unitario',
            'Costo total',
            'Usuario',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Kardex::where('id_producto', $request->id_producto)
            ->when($request->id_inventario, function ($q) use ($request) {
                $q->where('id_inventario', $request->id_inventario);
            })
            ->when($request->inicio, function ($q) use ($request) {
                $q->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($q) use ($request) {
                $q->where('fecha', '<=', $request->fin);
            })
            ->when($request->detalle, function ($q) use ($request) {
                return $q->where('detalle', 'like', '%' . $request->detalle . '%');
            })
            ->orderBy($request->orden ?? 'fecha', $request->direccion ?? 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->fecha,
            $row->producto()->pluck('nombre')->first(),
            $row->inventario()->pluck('nombre')->first(),
            $row->numero_lote ?? '',
            $row->detalle,
            $row->modelo_detalle,
            $row->entrada_cantidad,
            $row->salida_cantidad,
            $row->total_cantidad,
            $row->costo_unitario,
            $row->total_valor,
            $row->usuario()->pluck('name')->first(),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E9'],
                ],
            ],
        ];
    }
}
