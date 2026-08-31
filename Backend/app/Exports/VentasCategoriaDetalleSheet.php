<?php

namespace App\Exports;

use App\Exports\Support\DetallesConDevolucionesQuery;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentasCategoriaDetalleSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithMapping
{
    protected $request;
    private $fecha_inicio;
    private $fecha_fin;
    private $sucursales = [];
    private $datosPorCategoria = [];

    public function __construct($request)
    {
        $this->request = $request;

        $this->fecha_inicio = $request->inicio ?? DB::table('ventas')->where('id_empresa', $request->id_empresa)->min('fecha');
        $this->fecha_fin = $request->fin ?? DB::table('ventas')->where('id_empresa', $request->id_empresa)->max('fecha');
    }

    public function title(): string
    {
        return 'Reporte por Categoría (Detalle por Sucursal)';
    }

    public function styles(Worksheet $sheet)
    {
        // Obtener el número de columnas dinámicamente
        $numSucursales = count($this->sucursales);
        $lastCol = $this->getColumnLetter(1 + $numSucursales + 2); // Categoría + Sucursales + Total Unidades + Total Ventas
        
        $headerRow = 6;
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
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
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                ]
            ]
        ]);

        $totalRow = $lastRow;
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font' => [
                'bold' => true
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'E2EFDA']
            ]
        ]);

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(30);
        for ($i = 0; $i < $numSucursales; $i++) {
            $col = $this->getColumnLetter(2 + $i);
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        $sheet->getColumnDimension($this->getColumnLetter(2 + $numSucursales))->setWidth(20);
        $sheet->getColumnDimension($this->getColumnLetter(2 + $numSucursales + 1))->setWidth(25);

        return [];
    }

    private function getColumnLetter($num)
    {
        $letter = '';
        while ($num > 0) {
            $num--;
            $letter = chr(65 + ($num % 26)) . $letter;
            $num = intval($num / 26);
        }
        return $letter;
    }

    public function headings(): array
    {
        $sucursalesTexto = '';
        if ($this->request->sucursales && count($this->request->sucursales) > 0) {
            $sucursalesTexto = implode(', ', DB::table('sucursales')
                ->whereIn('id', $this->request->sucursales)
                ->pluck('nombre')
                ->toArray());
        } else {
            $sucursalesTexto = 'Todas';
        }

        $query = DetallesConDevolucionesQuery::sucursalesVenta(
            $this->request,
            (string) $this->fecha_inicio,
            (string) $this->fecha_fin
        );

        $this->sucursales = $query->pluck('nombre')->toArray();

        // Construir el encabezado dinámico
        $headers = ['Categoría'];
        foreach ($this->sucursales as $nombre) {
            $headers[] = $nombre;
        }
        $headers[] = 'Total Unidades';
        $headers[] = 'Total Ventas (Sin IVA)';

        return [
            ['Reporte de Ventas - Por Categoría (Detalle por Sucursal)'],
            ['Fecha Inicio:', $this->fecha_inicio],
            ['Fecha Final:', $this->fecha_fin],
            ['Sucursales:', $sucursalesTexto],
            [''],
            $headers
        ];
    }

    public function collection()
    {
        $request = $this->request;

        // Si las sucursales no se obtuvieron en headings(), obtenerlas aquí
        if (empty($this->sucursales)) {
            $query = DetallesConDevolucionesQuery::sucursalesVenta(
                $this->request,
                (string) $this->fecha_inicio,
                (string) $this->fecha_fin
            );
            $this->sucursales = $query->pluck('nombre')->toArray();
        }

        $lineas = DetallesConDevolucionesQuery::lineasVenta(
            $request,
            (string) $this->fecha_inicio,
            (string) $this->fecha_fin
        );

        $detalles = DB::query()
            ->fromSub($lineas, 'lineas')
            ->join('productos', 'productos.id', '=', 'lineas.id_producto')
            ->join('categorias', 'categorias.id', '=', 'productos.id_categoria')
            ->join('sucursales', 'sucursales.id', '=', 'lineas.id_sucursal')
            ->select(
                'categorias.id as id_categoria',
                'categorias.nombre as categoria',
                'sucursales.id as id_sucursal',
                'sucursales.nombre as sucursal',
                DB::raw('SUM(lineas.cantidad) as unidades_vendidas'),
                DB::raw('SUM(lineas.total) as total_ventas')
            )
            ->when($request->categorias, function ($query) use ($request) {
                return $query->whereIn('productos.id_categoria', $request->categorias);
            })
            ->when($request->marcas, function ($query) use ($request) {
                return $query->whereIn('productos.marca', $request->marcas);
            })
            ->groupBy('categorias.id', 'categorias.nombre', 'sucursales.id', 'sucursales.nombre')
            ->orderBy('categorias.nombre')
            ->get();

        // Organizar datos por categoría
        $datosPorCategoria = [];
        foreach ($detalles as $detalle) {
            $categoriaId = $detalle->id_categoria;
            $categoriaNombre = $detalle->categoria;
            $sucursalNombre = $detalle->sucursal;
            
            if (!isset($datosPorCategoria[$categoriaId])) {
                $datosPorCategoria[$categoriaId] = [
                    'id_categoria' => $categoriaId,
                    'categoria' => $categoriaNombre,
                    'sucursales' => [],
                    'total_unidades' => 0,
                    'total_ventas' => 0
                ];
            }
            
            $datosPorCategoria[$categoriaId]['sucursales'][$sucursalNombre] = [
                'unidades' => $detalle->unidades_vendidas,
                'ventas' => $detalle->total_ventas
            ];
            
            $datosPorCategoria[$categoriaId]['total_unidades'] += $detalle->unidades_vendidas;
            $datosPorCategoria[$categoriaId]['total_ventas'] += $detalle->total_ventas;
        }

        $this->datosPorCategoria = $datosPorCategoria;
        $total_ventas = collect($datosPorCategoria)->sum('total_ventas');

        // Convertir a colección para el formato esperado
        $resultado = collect($datosPorCategoria)->map(function ($item) {
            return (object) $item;
        });

        // Agregar fila de totales
        $totales = [
            'unidades_por_sucursal' => [],
            'total_unidades' => 0,
            'total_ventas' => 0
        ];

        foreach ($this->sucursales as $sucursalNombre) {
            $totalSucursal = 0;
            foreach ($datosPorCategoria as $cat) {
                $totalSucursal += $cat['sucursales'][$sucursalNombre]['unidades'] ?? 0;
            }
            $totales['unidades_por_sucursal'][$sucursalNombre] = $totalSucursal;
            $totales['total_unidades'] += $totalSucursal;
        }
        $totales['total_ventas'] = $total_ventas;

        $resultado->push((object) [
            'id_categoria' => null,
            'categoria' => 'TOTAL',
            'sucursales' => $totales['unidades_por_sucursal'],
            'total_unidades' => $totales['total_unidades'],
            'total_ventas' => $totales['total_ventas']
        ]);

        return $resultado;
    }

    public function map($row): array
    {
        if ($row->id_categoria === null) {
            // Fila de totales
            $resultado = ['TOTAL'];
            
            foreach ($this->sucursales as $sucursalNombre) {
                $resultado[] = $row->sucursales[$sucursalNombre] ?? 0;
            }
            
            $resultado[] = $row->total_unidades;
            $resultado[] = '$' . number_format(round($row->total_ventas, 2), 2);
            
            return $resultado;
        }

        // Fila de categoría
        $resultado = [$row->categoria];
        
        // Agregar unidades por cada sucursal
        foreach ($this->sucursales as $sucursalNombre) {
            $resultado[] = $row->sucursales[$sucursalNombre]['unidades'] ?? 0;
        }
        
        // Agregar totales
        $resultado[] = $row->total_unidades;
        $resultado[] = '$' . number_format(round($row->total_ventas, 2), 2);
        
        return $resultado;
    }
}
