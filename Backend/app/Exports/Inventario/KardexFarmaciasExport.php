<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Http\Request;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class KardexFarmaciasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array
    {
        return [
            'N° Correlativo',
            'Fecha',
            'N° del Documento',
            'Nombre del Proveedor',
            'Nacionalidad del Proveedor',
            'Descripción del Producto',
            'Fuente de Referencia',
            'Entrada Cantidad',
            'Entrada Costo Uni',
            'Entrada Total',
            'Salida Cantidad',
            'Salida Costo Uni',
            'Salida Total',
            'Existencia Cantidad',
            'Existencia Costo Uni',
            'Existencia Total',
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
        static $correlativo = 0;
        $correlativo++;

        return [
            $correlativo,
            $row->fecha,
            $row->modelo_detalle ?? '',
            $row->nombre_proveedor_origen ?? '',
            $row->nacionalidad_proveedor ?? '',
            $row->nombre_producto ?? '',
            $row->detalle ?? '',
            $row->entrada_cantidad ?? 0,
            $row->entrada_cantidad > 0 ? $row->costo_unitario : '',
            $row->entrada_cantidad > 0 ? $row->entrada_valor : '',
            $row->salida_cantidad ?? 0,
            $row->salida_cantidad > 0 ? $row->precio_unitario : '',
            $row->salida_cantidad > 0 ? $row->salida_valor : '',
            $row->total_cantidad ?? 0,
            $row->costo_unitario ?? '',
            $row->total_valor ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        $request = $this->request;
        $empresa = Auth::user()->empresa ?? Empresa::find(Auth::user()->id_empresa ?? null);
        $producto = $request->id_producto ? Producto::withoutGlobalScopes()->find($request->id_producto) : null;
        $lote = $request->lote_id ? Lote::find($request->lote_id) : null;

        $nombreEmpresa = $empresa ? ($empresa->nombre ?? '') : '';
        $nit = $empresa ? ($empresa->nit ?? '') : '';
        $nrc = $empresa ? ($empresa->ncr ?? '') : '';
        $nombreProducto = $producto ? ($producto->nombre ?? '') : '—';
        $medida = $producto ? ($producto->medida ?? '—') : '—';
        $numeroLote = $lote ? ($lote->numero_lote ?? '—') : '—';

        $inicio = $request->inicio ? Carbon::parse($request->inicio)->format('d/m/Y') : '';
        $fin = $request->fin ? Carbon::parse($request->fin)->format('d/m/Y') : '';
        $textoRango = $inicio && $fin ? "DEL {$inicio} AL {$fin}" : '';

        return [
            AfterSheet::class => function (AfterSheet $event) use ($nombreEmpresa, $nit, $nrc, $nombreProducto, $medida, $numeroLote, $textoRango) {
                $sheet = $event->sheet->getDelegate();

                // Insertar 6 filas al inicio para el encabezado
                $sheet->insertNewRowBefore(1, 6);

                // Fila 1: Nombre de la empresa (centrado, mayúsculas, negrita)
                $sheet->setCellValue('A1', strtoupper($nombreEmpresa));
                $sheet->mergeCells('A1:P1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                // Fila 2: TARJETA DE CONTROL DE INVENTARIOS DE MATERIALES DEL ... AL ...
                $sheet->setCellValue('A2', 'TARJETA DE CONTROL DE INVENTARIOS DE MATERIALES ' . $textoRango);
                $sheet->mergeCells('A2:P2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2')->getFont()->setSize(10);

                // Fila 3: COSTO PROMEDIO
                $sheet->setCellValue('A3', 'COSTO PROMEDIO');
                $sheet->mergeCells('A3:P3');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);

                // Fila 4: NIT y NRC (NIT a la izquierda, NRC a la derecha)
                $sheet->setCellValue('A4', 'NIT: ' . $nit);
                $sheet->setCellValue('P4', 'NRC: ' . $nrc);
                $sheet->getStyle('A4')->getFont()->setSize(9);
                $sheet->getStyle('P4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('P4')->getFont()->setSize(9);

                // Fila 5: ARTÍCULO, TAMAÑO, LOTE
                $sheet->setCellValue('A5', 'ARTÍCULO: ' . $nombreProducto);
                $sheet->setCellValue('F5', 'TAMAÑO: ' . $medida);
                $sheet->setCellValue('L5', 'LOTE: ' . $numeroLote);
                $sheet->getStyle('A5:P5')->getFont()->setSize(9);
                $sheet->getStyle('F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('L5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Fila 6: vacía (separación)
                // Fila 7: encabezados de la tabla (ya escritos por WithHeadings, ahora en row 7)
                $headerRow = 7;
                $sheet->getStyle('A' . $headerRow . ':P' . $headerRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . $headerRow . ':P' . $headerRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E8F5E9');
                $sheet->getStyle('A' . $headerRow . ':P' . $headerRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            },
        ];
    }
}
