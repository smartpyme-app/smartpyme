<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Http\Request;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class KardexExport implements FromCollection, WithHeadings, WithMapping, WithEvents
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
            'Producto',
            'Inventario',
            'Detalle',
            'N° Documento',
            'Nombre del proveedor',
            'Entrada',
            'Salida',
            'Stock',
            'Costo U',
            'Costo Total',
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
            $row->detalle,
            $row->modelo_detalle,
            $row->nombre_proveedor_origen ?? '',
            $row->entrada_cantidad,
            $row->salida_cantidad,
            $row->total_cantidad,
            $row->costo_unitario,
            $row->total_valor,
            $row->usuario()->pluck('name')->first(),
        ];
    }

    public function registerEvents(): array
    {
        $request = $this->request;
        $empresa = Auth::user()->empresa ?? Empresa::find(Auth::user()->id_empresa ?? null);
        $producto = $request->id_producto ? Producto::withoutGlobalScopes()->find($request->id_producto) : null;

        $nombreEmpresa = $empresa ? ($empresa->nombre ?? '') : '';
        $nit = $empresa ? ($empresa->nit ?? '') : '';
        $nrc = $empresa ? ($empresa->ncr ?? '') : '';
        $nombreProducto = $producto ? ($producto->nombre ?? '') : '—';
        $medida = $producto ? ($producto->medida ?? '—') : '—';

        $inicio = $request->inicio ? Carbon::parse($request->inicio)->format('d/m/Y') : '';
        $fin = $request->fin ? Carbon::parse($request->fin)->format('d/m/Y') : '';
        $textoRango = $inicio && $fin ? "DEL {$inicio} AL {$fin}" : '';

        $lastCol = 'L'; // 12 columnas

        return [
            AfterSheet::class => function (AfterSheet $event) use ($nombreEmpresa, $nit, $nrc, $nombreProducto, $medida, $textoRango, $lastCol) {
                $sheet = $event->sheet->getDelegate();

                // Insertar 6 filas al inicio para el encabezado
                $sheet->insertNewRowBefore(1, 6);

                // Fila 1: Nombre de la empresa (centrado, mayúsculas, negrita)
                $sheet->setCellValue('A1', strtoupper($nombreEmpresa));
                $sheet->mergeCells('A1:' . $lastCol . '1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                // Fila 2: TARJETA DE CONTROL DE INVENTARIOS DE MATERIALES DEL ... AL ...
                $sheet->setCellValue('A2', 'TARJETA DE CONTROL DE INVENTARIOS DE MATERIALES ' . $textoRango);
                $sheet->mergeCells('A2:' . $lastCol . '2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFont()->setSize(10);

                // Fila 3: COSTO PROMEDIO
                $sheet->setCellValue('A3', 'COSTO PROMEDIO');
                $sheet->mergeCells('A3:' . $lastCol . '3');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                // Fila 4: NIT y NRC
                $sheet->setCellValue('A4', 'NIT: ' . $nit);
                $sheet->setCellValue($lastCol . '4', 'NRC: ' . $nrc);
                $sheet->getStyle('A4')->getFont()->setSize(9);
                $sheet->getStyle($lastCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($lastCol . '4')->getFont()->setSize(9);

                // Fila 5: ARTÍCULO y TAMAÑO (sin Lote)
                $sheet->setCellValue('A5', 'ARTÍCULO: ' . $nombreProducto);
                $sheet->setCellValue($lastCol . '5', 'TAMAÑO: ' . $medida);
                $sheet->getStyle('A5:' . $lastCol . '5')->getFont()->setSize(9);
                $sheet->getStyle($lastCol . '5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Fila 6: vacía. Fila 7: encabezados de la tabla
                $headerRow = 7;
                $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E8F5E9');
                $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            },
        ];
    }
}
