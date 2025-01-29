<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentasCategoriaSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithMapping
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function title(): string
    {
        return 'Reporte por Categoría';
    }

    public function styles(Worksheet $sheet)
    {

        $sheet->getStyle('A6:C6')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '2F5233']
            ],
            'font' => [
                'color' => ['rgb' => 'FFFFFF'],
                'bold' => true
            ]
        ]);


        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14
            ]
        ]);


        $sheet->getStyle('A2:A4')->applyFromArray([
            'font' => [
                'bold' => true
            ]
        ]);


        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A6:C' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);


        $totalRow = $lastRow;
        $sheet->getStyle("A{$totalRow}:C{$totalRow}")->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E2EFDA']
            ]
        ]);


        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(25);
        // $sheet->getColumnDimension('D')->setWidth(25);

        return [];
    }

    public function headings(): array
    {
        $sucursales = '';
        if ($this->request->sucursales && count($this->request->sucursales) > 0) {
            $sucursales = implode(', ', DB::table('sucursales')
                ->whereIn('id', $this->request->sucursales)
                ->pluck('nombre')
                ->toArray());
        } else {
            $sucursales = 'Todas';
        }

        $fecha_inicio = $this->request->inicio ?? DB::table('ventas')->min('fecha');

        $fecha_fin = $this->request->fin ?? DB::table('ventas')->max('fecha');


        return [
            ['Reporte de Ventas - Por Categoría'],
            ['Fecha Inicio:', $fecha_inicio],
            ['Fecha Final:', $fecha_fin],
            ['Sucursal:', $sucursales],
            [''],
            [
                'Categoría',
                'Unidades Vendidas (#)',
                'Total de Ventas (Sin IVA)',
                // '% del Total'
            ]
        ];
    }

    public function collection()
    {
        $request = $this->request;

        $detalles = DB::table('detalles_venta')
            ->join('ventas', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->join('productos', 'productos.id', '=', 'detalles_venta.id_producto')
            ->join('categorias', 'categorias.id', '=', 'productos.id_categoria')
            ->select(
                'categorias.id',
                'categorias.nombre as categoria',
                DB::raw('SUM(detalles_venta.cantidad) as unidades_vendidas'),
                DB::raw('SUM(detalles_venta.total) as total_ventas')
            )
            ->when($request->inicio, function ($query) use ($request) {
                return $query->whereBetween('ventas.fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->sucursales, function ($query) use ($request) {
                return $query->whereIn('ventas.id_sucursal', $request->sucursales);
            })
            ->when($request->categorias, function ($query) use ($request) {
                return $query->whereIn('productos.id_categoria', $request->categorias);
            })
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->groupBy('categorias.id', 'categorias.nombre')
            ->orderBy('total_ventas', 'desc')
            ->get();

        $total_ventas = $detalles->sum('total_ventas');

        // Calcular porcentajes
        // $detalles = $detalles->map(function ($item) use ($total_ventas) {
        //     $item->porcentaje = $total_ventas > 0 ? ($item->total_ventas / $total_ventas) * 100 : 0;
        //     return $item;
        // });

        // Agregar fila de totales
        $detalles->push((object)[
            'id' => null,
            'categoria' => 'TOTAL',
            'unidades_vendidas' => $detalles->sum('unidades_vendidas'),
            'total_ventas' => $total_ventas,
            //  'porcentaje' => 100
        ]);

        return $detalles;
    }

    public function map($row): array
    {
        return [
            $row->categoria,
            $row->unidades_vendidas,
            '$' . number_format(round($row->total_ventas, 2), 2),
            //number_format(round($row->porcentaje, 4), 4) . '%'
        ];
    }
}
