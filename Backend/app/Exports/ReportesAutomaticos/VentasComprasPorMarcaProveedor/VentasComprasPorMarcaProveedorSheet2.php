<?php

namespace App\Exports\ReportesAutomaticos\VentasComprasPorMarcaProveedor;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class VentasComprasPorMarcaProveedorSheet2 implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    public $fechaInicio;
    public $fechaFin;
    public $id_empresa;
    public $sucursales;
    private $datos;

    public function __construct($fechaInicio, $fechaFin, $id_empresa, $sucursales = [])
    {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->id_empresa = $id_empresa;
        $this->sucursales = $sucursales;
    }

    public function title(): string
    {
        return 'Resumen por Proveedor';
    }

    public function headings(): array
    {
        return [
            'Proveedor',
            'Marca',
            'Cantidad comprada',
            'Compras totales',
            'Cantidad vendida',
            'Ventas totales',
            'Utilidad'
        ];
    }

    public function collection()
    {
        // Construir condiciones WHERE para sucursales
        $whereSucursales = '';
        $bindings = [$this->id_empresa, $this->fechaInicio, $this->fechaFin];
        
        if (!empty($this->sucursales)) {
            $placeholders = str_repeat('?,', count($this->sucursales) - 1) . '?';
            $whereSucursales = "AND v.id_sucursal IN ({$placeholders})";
            $bindings = array_merge($bindings, $this->sucursales);
        }

        // Consulta para obtener compras agrupadas por proveedor y marca
        $comprasQuery = "
            SELECT 
                COALESCE(
                    CASE 
                        WHEN pr.tipo = 'Persona' THEN CONCAT(pr.nombre, ' ', pr.apellido)
                        ELSE pr.nombre_empresa
                    END,
                    'Sin proveedor'
                ) AS proveedor,
                COALESCE(p.marca, 'Sin marca') AS marca,
                SUM(dc.cantidad) AS cantidad_comprada,
                SUM(dc.total) AS compras_totales
            FROM detalles_compra dc
            INNER JOIN compras c ON c.id = dc.id_compra
            INNER JOIN productos p ON p.id = dc.id_producto
            LEFT JOIN proveedores pr ON pr.id = c.id_proveedor
            WHERE c.id_empresa = ?
                AND c.estado != 'Anulada'
                AND c.cotizacion = 0
                AND c.fecha BETWEEN ? AND ?
            GROUP BY proveedor, marca
        ";

        $compras = DB::select($comprasQuery, [$this->id_empresa, $this->fechaInicio, $this->fechaFin]);

        // Consulta para obtener ventas agrupadas por proveedor y marca
        // Usamos una subconsulta para obtener el primer proveedor de cada producto
        $ventasQuery = "
            SELECT 
                COALESCE(
                    CASE 
                        WHEN pr.tipo = 'Persona' THEN CONCAT(pr.nombre, ' ', pr.apellido)
                        ELSE pr.nombre_empresa
                    END,
                    'Sin proveedor'
                ) AS proveedor,
                COALESCE(p.marca, 'Sin marca') AS marca,
                SUM(dv.cantidad) AS cantidad_vendida,
                SUM(dv.total) AS ventas_totales
            FROM detalles_venta dv
            INNER JOIN ventas v ON v.id = dv.id_venta
            INNER JOIN productos p ON p.id = dv.id_producto
            LEFT JOIN (
                SELECT pp1.id_producto, pp1.id_proveedor
                FROM producto_proveedores pp1
                INNER JOIN (
                    SELECT id_producto, MIN(id) as min_id
                    FROM producto_proveedores
                    GROUP BY id_producto
                ) pp2 ON pp1.id_producto = pp2.id_producto AND pp1.id = pp2.min_id
            ) pp ON pp.id_producto = p.id
            LEFT JOIN proveedores pr ON pr.id = pp.id_proveedor
            WHERE v.id_empresa = ?
                AND v.estado != 'Anulada'
                AND v.cotizacion = 0
                AND v.fecha BETWEEN ? AND ?
                " . ($whereSucursales ?: '') . "
            GROUP BY proveedor, marca
        ";

        $ventas = DB::select($ventasQuery, $bindings);

        // Combinar compras y ventas
        $datosCombinados = [];

        // Procesar compras
        foreach ($compras as $compra) {
            $key = $compra->proveedor . '|' . $compra->marca;
            $datosCombinados[$key] = [
                'proveedor' => $compra->proveedor,
                'marca' => $compra->marca,
                'cantidad_comprada' => $compra->cantidad_comprada,
                'compras_totales' => $compra->compras_totales,
                'cantidad_vendida' => 0,
                'ventas_totales' => 0,
            ];
        }

        // Procesar ventas
        foreach ($ventas as $venta) {
            $key = $venta->proveedor . '|' . $venta->marca;
            if (isset($datosCombinados[$key])) {
                $datosCombinados[$key]['cantidad_vendida'] = $venta->cantidad_vendida;
                $datosCombinados[$key]['ventas_totales'] = $venta->ventas_totales;
            } else {
                $datosCombinados[$key] = [
                    'proveedor' => $venta->proveedor,
                    'marca' => $venta->marca,
                    'cantidad_comprada' => 0,
                    'compras_totales' => 0,
                    'cantidad_vendida' => $venta->cantidad_vendida,
                    'ventas_totales' => $venta->ventas_totales,
                ];
            }
        }

        // Ordenar por proveedor y luego por marca
        uasort($datosCombinados, function($a, $b) {
            if ($a['proveedor'] === $b['proveedor']) {
                return strcmp($a['marca'], $b['marca']);
            }
            return strcmp($a['proveedor'], $b['proveedor']);
        });

        // Calcular utilidad y preparar datos para la colección
        $collection = collect();
        $totales = [
            'cantidad_comprada' => 0,
            'compras_totales' => 0,
            'cantidad_vendida' => 0,
            'ventas_totales' => 0,
            'utilidad' => 0,
        ];

        foreach ($datosCombinados as $dato) {
            $utilidad = $dato['ventas_totales'] - $dato['compras_totales'];
            
            $collection->push((object) [
                'proveedor' => $dato['proveedor'],
                'marca' => $dato['marca'],
                'cantidad_comprada' => $dato['cantidad_comprada'],
                'compras_totales' => $dato['compras_totales'],
                'cantidad_vendida' => $dato['cantidad_vendida'],
                'ventas_totales' => $dato['ventas_totales'],
                'utilidad' => $utilidad,
            ]);

            $totales['cantidad_comprada'] += $dato['cantidad_comprada'];
            $totales['compras_totales'] += $dato['compras_totales'];
            $totales['cantidad_vendida'] += $dato['cantidad_vendida'];
            $totales['ventas_totales'] += $dato['ventas_totales'];
            $totales['utilidad'] += $utilidad;
        }

        // Agregar fila de totales
        $collection->push((object) [
            'proveedor' => 'TOTAL',
            'marca' => '',
            'cantidad_comprada' => $totales['cantidad_comprada'],
            'compras_totales' => $totales['compras_totales'],
            'cantidad_vendida' => $totales['cantidad_vendida'],
            'ventas_totales' => $totales['ventas_totales'],
            'utilidad' => $totales['utilidad'],
            'es_total' => true,
        ]);

        $this->datos = $collection;
        return $collection;
    }

    public function map($row): array
    {
        return [
            $row->proveedor,
            $row->marca,
            $row->cantidad_comprada ?? 0,
            $row->compras_totales ?? 0,
            $row->cantidad_vendida ?? 0,
            $row->ventas_totales ?? 0,
            $row->utilidad ?? 0,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Estilo para los encabezados
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ]);

        // Estilo para la fila de totales
        if ($lastRow > 1) {
            $sheet->getStyle("A{$lastRow}:G{$lastRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ]);
        }

        // Aplicar bordes a toda la tabla
        $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Alternar colores de fila para mejor legibilidad
        for ($row = 2; $row < $lastRow; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA']
                    ]
                ]);
            }
        }

        // Colorear utilidad positiva en verde y negativa en rojo
        for ($row = 2; $row < $lastRow; $row++) {
            $utilidadCell = 'G' . $row;
            $utilidadValue = $sheet->getCell($utilidadCell)->getValue();
            
            if (is_numeric($utilidadValue)) {
                if ($utilidadValue > 0) {
                    $sheet->getStyle($utilidadCell)->applyFromArray([
                        'font' => [
                            'color' => ['rgb' => '00AA00'],
                            'bold' => true
                        ]
                    ]);
                } elseif ($utilidadValue < 0) {
                    $sheet->getStyle($utilidadCell)->applyFromArray([
                        'font' => [
                            'color' => ['rgb' => 'FF0000'],
                            'bold' => true
                        ]
                    ]);
                }
            }
        }

        return [];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_CURRENCY_USD, // Compras totales
            'F' => NumberFormat::FORMAT_CURRENCY_USD, // Ventas totales
            'G' => NumberFormat::FORMAT_CURRENCY_USD, // Utilidad
        ];
    }
}
