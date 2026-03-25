<?php

namespace App\Exports\ReportesAutomaticos\InventarioPorSucursal;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InventarioExport implements FromCollection, WithHeadings, WithStyles, WithTitle, WithColumnFormatting, ShouldAutoSize
{
    protected $datos;
    protected $configuracion;
    protected $fechaInicio;
    protected $fechaFin;

    public function __construct($datos, $configuracion, $fechaInicio, $fechaFin)
    {
        $this->datos = collect($datos);
        $this->configuracion = $configuracion;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function collection()
    {
        // Filtrar solo los productos (no los headers de sucursal o bodega)
        $productos = $this->datos->where('tipo', 'producto');
        
        return $productos->map(function ($item) {
            return [
                'sucursal' => $item['sucursal_nombre'],
                'bodega' => $item['bodega_nombre'],
                'categoria' => $item['categoria_nombre'],
                'codigo' => $item['producto_codigo'],
                'producto' => $item['producto_nombre'],
                'proveedor' => $item['nombre_proveedor'],
                'cantidad_actual' => $item['cantidad_actual'],
                'precio_unitario' => $item['precio_unitario'],
                'costo_unitario' => $item['costo_unitario'],
                'valor_inventario' => $item['valor_inventario'],
                'precio_total' => $item['precio_total'],
                'costo_total' => $item['costo_total'],
                'estado_stock' => $item['estado_stock'],
                'ultima_actualizacion' => $item['ultima_actualizacion']
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Sucursal',
            'Bodega',
            'Categoría',
            'Código',
            'Producto',
            'Proveedor',
            'Cantidad Actual',
            'Precio Unitario',
            'Costo Unitario',
            'Valor Inventario',
            'Precio Total',
            'Costo Total',
            'Estado Stock',
            'Última Actualización'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para los encabezados (ahora son 16 columnas: A-P)
        $sheet->getStyle('A1:N1')->applyFromArray([
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

        // Aplicar bordes a toda la tabla
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Estilo condicional para el estado del stock (columna O)
        for ($row = 2; $row <= $lastRow; $row++) {
            $estadoCell = 'M' . $row;
            $estadoValue = $sheet->getCell($estadoCell)->getValue();
            
            if ($estadoValue === 'Bajo') {
                $sheet->getStyle($estadoCell)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFCCCC'] // Rojo claro
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'CC0000']
                    ]
                ]);
            } elseif ($estadoValue === 'Alto') {
                $sheet->getStyle($estadoCell)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFCC'] // Amarillo claro
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'CC6600']
                    ]
                ]);
            } else {
                $sheet->getStyle($estadoCell)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCFFCC'] // Verde claro
                    ],
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '006600']
                    ]
                ]);
            }
        }

        // Alternar colores de fila para mejor legibilidad
        for ($row = 2; $row <= $lastRow; $row++) {
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA'] // Gris muy claro
                    ]
                ]);
            }
        }

        return [];
    }

    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_CURRENCY_USD, // Precio Unitario
            'I' => NumberFormat::FORMAT_CURRENCY_USD, // Costo Unitario
            'J' => NumberFormat::FORMAT_CURRENCY_USD, // Valor Inventario
            'K' => NumberFormat::FORMAT_CURRENCY_USD, // Precio Total
            'L' => NumberFormat::FORMAT_CURRENCY_USD, // Costo Total
            'N' => NumberFormat::FORMAT_DATE_DATETIME, // Última Actualización
        ];
    }

    public function title(): string
    {
        return 'Inventario ' . $this->fechaInicio . ' al ' . $this->fechaFin;
    }
}