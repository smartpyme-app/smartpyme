<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;

class RentabilidadSucursalExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public $request;
    private $fecha_inicio;
    private $fecha_fin;

    public function filter(Request $request)
    {
        $this->request = $request;
        $this->fecha_inicio = $request->inicio ?? DB::table('compras')->where('id_empresa', $request->id_empresa)->min('fecha');
        $this->fecha_fin = $request->fin ?? DB::table('compras')->where('id_empresa', $request->id_empresa)->max('fecha');
    }




    public function title(): string
    {
        return 'Reporte de Rentabilidad - Compras';
    }

    public function collection()
    {
        $request = $this->request;

        return DB::table('detalles_compra as dc')
            ->join('productos', 'dc.id_producto', '=', 'productos.id')
            ->join('categorias', 'productos.id_categoria', '=', 'categorias.id')
            ->join('compras', 'dc.id_compra', '=', 'compras.id')
            ->join('detalles_venta as dv', 'dc.id_producto', '=', 'dv.id_producto')
            ->join('ventas', 'dv.id_venta', '=', 'ventas.id')
            ->where('compras.id_empresa', $request->id_empresa)
            ->when($this->fecha_inicio, function ($query) {
                return $query->whereBetween('ventas.fecha', [$this->fecha_inicio, $this->fecha_fin]);
            })
            ->when($request->sucursales, function ($query) use ($request) {
                return $query->whereIn('compras.id_sucursal', $request->sucursales);
            })
            ->select(
                'categorias.nombre as categoria',
                'productos.nombre as producto',
                DB::raw('SUM(dv.cantidad) as unidades_vendidas'),
                DB::raw('SUM(dc.cantidad) as unidades_compradas'),
                DB::raw('SUM(dv.cantidad * dv.precio) as total_venta'),
                DB::raw('SUM(dc.cantidad * dc.costo) as total_compra'),
                DB::raw('(SUM(dv.cantidad * dv.precio) - (SUM(dv.cantidad) * (SUM(dc.cantidad * dc.costo) / SUM(dc.cantidad)))) as rentabilidad'),
                DB::raw('CASE 
                    WHEN SUM(dc.cantidad) = 0 OR SUM(dc.cantidad * dc.costo) = 0 THEN 0 
                    ELSE (((SUM(dv.cantidad * dv.precio) - (SUM(dv.cantidad) * (SUM(dc.cantidad * dc.costo) / SUM(dc.cantidad)))) / (SUM(dv.cantidad) * (SUM(dc.cantidad * dc.costo) / SUM(dc.cantidad)))) * 100) 
                END as rentabilidad_porcentaje')
            )
            ->groupBy('categorias.nombre', 'productos.nombre')
            ->orderBy('categorias.nombre')
            ->orderBy('productos.nombre')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->categoria,
            $row->producto,
            number_format($row->unidades_vendidas, 0),
            number_format($row->unidades_compradas, 0),
            number_format($row->total_venta, 2),
            number_format($row->total_compra, 2),
            number_format($row->rentabilidad, 2),
            number_format($row->rentabilidad_porcentaje, 2) . '%'
        ];
    }


    public function headings(): array
    {
        return [
            [
                'Reporte de Rentabilidad - Compras'
            ],
            [
                'Período: ' . $this->fecha_inicio . ' al ' . $this->fecha_fin
            ],
            [
                'Sucursales: ' . ($this->request->sucursales ? implode(
                    ', ',
                    DB::table('sucursales')
                        ->whereIn('id', $this->request->sucursales)
                        ->pluck('nombre')
                        ->toArray()
                )
                    : 'Todas')
            ],
            [], 
            [ 
                'Categoría',
                'Producto',
                'Unidades Vendidas (#)',
                'Unidades Compradas (#)',
                'Total Venta $',
                'Total Compra $',
                'Rentabilidad $',
                'Rentabilidad %'
            ]
        ];
    }

    public function styles(Worksheet $sheet)
    {
       
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ]
        ]);

        
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12
            ]
        ]);

        
        $sheet->mergeCells('A3:H3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12
            ]
        ]);

        
        $sheet->getStyle('A5:H5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '2F5233']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);

 
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);

      
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('E6:G' . $lastRow)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD);

        $sheet->getStyle('H6:H' . $lastRow)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);


        $sheet->getStyle('A6:H' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ]);


        $sheet->getStyle('C6:H' . $lastRow)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        return $sheet;
    }
}
