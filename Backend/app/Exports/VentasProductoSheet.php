<?php

namespace App\Exports;

use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Http\Request;

class VentasProductoSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    public $request;
    private $total_ventas = 0;
    private $fecha_inicio;
    private $fecha_fin;

    public function __construct($request)
    {
        $this->request = $request;

        $this->fecha_inicio = $request->inicio ?? DB::table('ventas')->where('id_empresa', $request->id_empresa)->min('fecha');
        $this->fecha_fin = $request->fin ?? DB::table('ventas')->where('id_empresa', $request->id_empresa)->max('fecha');
    }

    public function title(): string
    {
        return 'Reporte de Ventas - Acumulado por producto';
    }

    public function styles(Worksheet $sheet)
    {

        $sheet->getStyle('A6:F6')->applyFromArray([
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
        $sheet->getStyle('A6:F' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);


        $totalRow = $lastRow;
        $sheet->getStyle("A{$totalRow}:F{$totalRow}")->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E2EFDA']
            ]
        ]);


        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(25);

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

        // $this->fecha_inicio = $this->request->inicio ?? DB::table('ventas')->where('id_empresa', $this->request->id_empresa)->min('fecha');
        // $this->fecha_fin = $this->request->fin ?? DB::table('ventas')->where('id_empresa', $this->request->id_empresa)->max('fecha');

        return [
            ['Reporte de Ventas - Acumulado por producto'],
            ['Fecha Inicio:', $this->fecha_inicio],
            ['Fecha Final:', $this->fecha_fin],
            ['Sucursal:', $sucursales],
            [''],
            [
                'Categoría',
                'Marca',
                'SKU',
                'Unidades Vendidas (#)',
                'Total de Ventas (Sin IVA)',
                'Existencias Disponibles'
            ]
        ];
    }

    public function collection()
    {
        $request = $this->request;

        

        $detalles = Detalle::select(
            'productos.id_categoria',
            'productos.marca',
            'productos.nombre as sku',
            DB::raw('SUM(detalles_venta.cantidad) as unidades_vendidas'),
            DB::raw('SUM(detalles_venta.total) as total_ventas'),
            DB::raw('(SELECT SUM(stock) FROM inventario WHERE inventario.id_producto = productos.id) as existencias')
        )
            ->join('ventas', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->join('productos', 'productos.id', '=', 'detalles_venta.id_producto')
            ->when($this->fecha_inicio, function ($query) use ($request) {
                return $query->whereBetween('ventas.fecha', [$this->fecha_inicio, $this->fecha_fin]);
            })
            ->when($request->sucursales, function ($query) use ($request) {
                return $query->whereIn('ventas.id_sucursal', $request->sucursales);
            })
            ->when($request->categorias, function ($query) use ($request) {
                return $query->whereIn('productos.id_categoria', $request->categorias);
            })
            ->when($request->marcas, function ($query) use ($request) {
                return $query->whereIn('productos.marca', $request->marcas);
            })
            ->where('ventas.id_empresa', $request->id_empresa)
            ->where('ventas.estado', '!=', 'Anulada')
            ->where('ventas.cotizacion', 0)
            ->groupBy('productos.id', 'productos.id_categoria', 'productos.marca', 'productos.nombre')
            ->get();


        $this->total_ventas = $detalles->sum('total_ventas');

        $detalles->push((object)[
            'id_categoria' => null,
            'marca' => '',
            'sku' => 'TOTAL',
            'unidades_vendidas' => $detalles->sum('unidades_vendidas'),
            'total_ventas' => $this->total_ventas,
            'existencias' => null
        ]);

        return $detalles;
    }

    public function map($row): array
    {
        if ($row->id_categoria === null) {

            return [
                'TOTAL',
                '',
                '',
                $row->unidades_vendidas,
                '$' . number_format(round($row->total_ventas, 2), 2),
                $row->existencias
            ];
        }

        $categoria = DB::table('categorias')->where('id', $row->id_categoria)->value('nombre');

        return [
            $categoria,
            $row->marca,
            $row->sku,
            $row->unidades_vendidas,
            '$' . number_format(round($row->total_ventas, 2), 2),
            $row->existencias ?? 0
        ];
    }
}
