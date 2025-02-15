<?php

namespace App\Exports;

use App\Models\Venta\Detalle;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RentabilidadSucursalExport implements FromCollection, WithHeadings, WithMapping
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

    public function headings(): array
    {
        return [
            'Categoría',
            'Producto',
            'Unidades Vendidas (#)',
            'Unidades Compradas (#)',
            'Total Venta $',
            'Total Compra $',
            'Rentabilidad $',
            'Rentabilidad %',
        ];
    }

    public function title(): string
    {
        return 'Reporte de Rentabilidad - Compras';
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el título del reporte
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'Reporte de Rentabilidad por Sucursal');
        $sheet->getStyle('A1')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '2F5233']
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
        ]);

        // Información de fecha
        $sheet->setCellValue('A3', 'Período: ' . $this->fecha_inicio . ' al ' . $this->fecha_fin);

        // Estilo para los encabezados de columna
        $sheet->getStyle('A5:H5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '1F4E78']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Ajustar ancho de columnas específicamente
        $sheet->getColumnDimension('A')->setWidth(30); // Categoría
        $sheet->getColumnDimension('B')->setWidth(40); // Producto
        $sheet->getColumnDimension('C')->setWidth(20); // Unidades Vendidas
        $sheet->getColumnDimension('D')->setWidth(20); // Unidades Compradas
        $sheet->getColumnDimension('E')->setWidth(15); // Total Venta
        $sheet->getColumnDimension('F')->setWidth(15); // Total Compra
        $sheet->getColumnDimension('G')->setWidth(15); // Rentabilidad $
        $sheet->getColumnDimension('H')->setWidth(15); // Rentabilidad %

        // Estilo para las celdas de datos
        $lastRow = $sheet->getHighestRow();
        $dataRange = 'A6:H' . $lastRow;
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT
            ]
        ]);

        // Estilo para columnas numéricas
        $numericRange = 'C6:H' . $lastRow;
        $sheet->getStyle($numericRange)->applyFromArray([
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT
            ]
        ]);

        // Estilo zebra para las filas
        for ($i = 6; $i <= $lastRow; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle('A' . $i . ':H' . $i)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F8F9FA']
                    ]
                ]);
            }
        }

        // Formato de números
        $sheet->getStyle('E6:G' . $lastRow)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

        // Formato de porcentaje
        $sheet->getStyle('H6:H' . $lastRow)->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);

        return $sheet;
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
            ->when($this->fecha_inicio, function ($query) use ($request) {
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
                DB::raw('SUM(dv.cantidad * dv.precio) as ingresos'),
                DB::raw('SUM(dc.cantidad * dc.costo) as costos'),
                DB::raw('(SUM(dv.cantidad * dv.precio) - SUM(dc.cantidad * dc.costo)) as rentabilidad'),
                DB::raw('CASE WHEN SUM(dc.cantidad * dc.costo) = 0 THEN 0 ELSE ((SUM(dv.cantidad * dv.precio) - SUM(dc.cantidad * dc.costo)) / SUM(dc.cantidad * dc.costo) * 100) END as rentabilidad_porcentaje')
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
            $row->unidades_vendidas,
            $row->unidades_compradas,
            number_format($row->ingresos, 2),
            number_format($row->costos, 2),
            number_format($row->rentabilidad, 2),
            number_format($row->rentabilidad_porcentaje, 2) . '%'
        ];
    }
}
