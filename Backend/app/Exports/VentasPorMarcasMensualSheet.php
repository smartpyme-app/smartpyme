<?php

namespace App\Exports;

use App\Exports\Support\DetallesConDevolucionesQuery;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class VentasPorMarcasMensualSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    public $request;
    private $total_ventas = 0;
    private $fecha_inicio;
    private $fecha_fin;
    private $año;

    public function __construct($request)
    {
        $this->request = $request;
        
        // Optimización: Calcular fechas una sola vez
        $this->año = $request->año ?? date('Y');
        
        if (isset($request->inicio) && isset($request->fin)) {
            $this->fecha_inicio = $request->inicio;
            $this->fecha_fin = $request->fin;
            $this->año = Carbon::parse($request->inicio)->year;
        } else {
            $this->fecha_inicio = $this->año . '-01-01';
            $this->fecha_fin = $this->año . '-12-31';
        }
    }

    public function title(): string
    {
        return 'Reporte de Ventas - Mes por Marca';
    }

    public function styles(Worksheet $sheet)
    {
        // Optimización: Aplicar estilos de una vez
        $lastRow = $sheet->getHighestRow();
        
        // Estilos de encabezado
        $sheet->getStyle('A6:R6')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '2F5233']
            ],
            'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]
        ]);

        // Título principal
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14]
        ]);

        // Información del reporte
        $sheet->getStyle('A2:A4')->applyFromArray([
            'font' => ['bold' => true]
        ]);

        // Bordes y fila de totales
        $sheet->getStyle('A6:R' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);

        $sheet->getStyle("A{$lastRow}:R{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E2EFDA']
            ]
        ]);

        // Optimización: Configurar anchos de columnas en un array
        $columnWidths = [
            'A' => 25, 'B' => 30, 'C' => 10, 'D' => 15, 'E' => 12, 'F' => 12,
            'G' => 12, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 12, 'L' => 12,
            'M' => 12, 'N' => 12, 'O' => 12, 'P' => 12, 'Q' => 18, 'R' => 15
        ];

        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return [];
    }

    public function headings(): array
    {
        // Optimización: Cachear consulta de sucursales
        static $sucursales_cache = null;
        
        if ($sucursales_cache === null) {
            if ($this->request->sucursales && count($this->request->sucursales) > 0) {
                $sucursales_cache = implode(', ', DB::table('sucursales')
                    ->whereIn('id', $this->request->sucursales)
                    ->pluck('nombre')
                    ->toArray());
            } else {
                $sucursales_cache = 'Todas';
            }
        }

        return [
            ['Reporte de Ventas - Acumulado por marca'],
            ['Fecha Inicio:', $this->fecha_inicio],
            ['Fecha Final:', $this->fecha_fin],
            ['Sucursal:', $sucursales_cache],
            [''],
            [
                'Marca', 'SKU', 'Año', 'Unidades Vendidas (#)',
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
                'Total de Ventas (Sin IVA)', 'Existencias Disponibles'
            ]
        ];
    }

    public function collection()
    {
        $request = $this->request;
        $lineas = DetallesConDevolucionesQuery::lineasVenta($request, (string) $this->fecha_inicio, (string) $this->fecha_fin);

        $resultados = DB::query()
            ->fromSub($lineas, 'lineas')
            ->join('productos as p', 'p.id', '=', 'lineas.id_producto')
            ->leftJoin(DB::raw('(SELECT id_producto, SUM(stock) as stock_total FROM inventario GROUP BY id_producto) inv'), 'inv.id_producto', '=', 'p.id')
            ->whereNotNull('p.marca')
            ->where('p.marca', '!=', '')
            ->when($request->categorias && count($request->categorias) > 0, function ($query) use ($request) {
                return $query->whereIn('p.id_categoria', $request->categorias);
            })
            ->when($request->marcas && count($request->marcas) > 0, function ($query) use ($request) {
                return $query->whereIn('p.marca', $request->marcas);
            })
            ->select(
                'p.marca',
                'p.nombre as sku',
                DB::raw('YEAR(lineas.fecha) as año'),
                DB::raw('SUM(lineas.cantidad) as unidades_vendidas'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 1 THEN lineas.total ELSE 0 END), 2) as enero'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 2 THEN lineas.total ELSE 0 END), 2) as febrero'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 3 THEN lineas.total ELSE 0 END), 2) as marzo'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 4 THEN lineas.total ELSE 0 END), 2) as abril'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 5 THEN lineas.total ELSE 0 END), 2) as mayo'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 6 THEN lineas.total ELSE 0 END), 2) as junio'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 7 THEN lineas.total ELSE 0 END), 2) as julio'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 8 THEN lineas.total ELSE 0 END), 2) as agosto'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 9 THEN lineas.total ELSE 0 END), 2) as septiembre'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 10 THEN lineas.total ELSE 0 END), 2) as octubre'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 11 THEN lineas.total ELSE 0 END), 2) as noviembre'),
                DB::raw('ROUND(SUM(CASE WHEN MONTH(lineas.fecha) = 12 THEN lineas.total ELSE 0 END), 2) as diciembre'),
                DB::raw('ROUND(SUM(lineas.total), 2) as total_de_ventas_sin_iva'),
                DB::raw('COALESCE(inv.stock_total, 0) as existencias_disponibles')
            )
            ->groupBy('p.id', 'p.marca', 'p.nombre', DB::raw('YEAR(lineas.fecha)'), 'inv.stock_total')
            ->orderBy('p.marca')
            ->orderBy('año', 'desc')
            ->orderBy('total_de_ventas_sin_iva', 'desc')
            ->get();

        // Optimización: Procesar en una sola pasada
        $collection = collect();
        $totals = [
            'unidades_vendidas' => 0, 'enero' => 0, 'febrero' => 0, 'marzo' => 0,
            'abril' => 0, 'mayo' => 0, 'junio' => 0, 'julio' => 0, 'agosto' => 0,
            'septiembre' => 0, 'octubre' => 0, 'noviembre' => 0, 'diciembre' => 0,
            'total_anual' => 0, 'existencias' => 0
        ];

        foreach ($resultados as $item) {
            $row = (object) [
                'marca' => $item->marca,
                'sku' => $item->sku,
                'año' => $item->año,
                'unidades_vendidas' => $item->unidades_vendidas,
                'enero' => $item->enero,
                'febrero' => $item->febrero,
                'marzo' => $item->marzo,
                'abril' => $item->abril,
                'mayo' => $item->mayo,
                'junio' => $item->junio,
                'julio' => $item->julio,
                'agosto' => $item->agosto,
                'septiembre' => $item->septiembre,
                'octubre' => $item->octubre,
                'noviembre' => $item->noviembre,
                'diciembre' => $item->diciembre,
                'total_anual' => $item->total_de_ventas_sin_iva,
                'existencias' => $item->existencias_disponibles,
                'es_total' => false
            ];

            // Acumular totales en la misma iteración
            $totals['unidades_vendidas'] += $item->unidades_vendidas;
            $totals['enero'] += $item->enero;
            $totals['febrero'] += $item->febrero;
            $totals['marzo'] += $item->marzo;
            $totals['abril'] += $item->abril;
            $totals['mayo'] += $item->mayo;
            $totals['junio'] += $item->junio;
            $totals['julio'] += $item->julio;
            $totals['agosto'] += $item->agosto;
            $totals['septiembre'] += $item->septiembre;
            $totals['octubre'] += $item->octubre;
            $totals['noviembre'] += $item->noviembre;
            $totals['diciembre'] += $item->diciembre;
            $totals['total_anual'] += $item->total_de_ventas_sin_iva;
            $totals['existencias'] += $item->existencias_disponibles;

            $collection->push($row);
        }

        // Agregar fila de totales
        $collection->push((object) array_merge($totals, [
            'marca' => 'TOTAL',
            'sku' => '',
            'año' => '',
            'es_total' => true
        ]));

        $this->total_ventas = $totals['total_anual'];

        return $collection;
    }

    public function map($row): array
    {
        // Optimización: Función helper para formateo
        $formatNumber = function($value) {
            return number_format(round($value, 2), 2);
        };

        $baseData = [
            $row->marca,
            $row->sku,
            $row->año,
            $row->unidades_vendidas,
            $formatNumber($row->enero),
            $formatNumber($row->febrero),
            $formatNumber($row->marzo),
            $formatNumber($row->abril),
            $formatNumber($row->mayo),
            $formatNumber($row->junio),
            $formatNumber($row->julio),
            $formatNumber($row->agosto),
            $formatNumber($row->septiembre),
            $formatNumber($row->octubre),
            $formatNumber($row->noviembre),
            $formatNumber($row->diciembre),
            $formatNumber($row->total_anual),
            $row->existencias ?? 0
        ];

        return $baseData;
    }
}