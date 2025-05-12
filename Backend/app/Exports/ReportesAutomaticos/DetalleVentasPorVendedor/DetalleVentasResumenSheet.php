<?php

namespace App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;

class DetalleVentasResumenSheet implements FromCollection, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    private $titulo;
    private $ventas;
    private $vendedores;

    public function __construct($titulo, $ventas, $vendedores)
    {
        $this->titulo = $titulo;
        $this->ventas = $ventas;
        $this->vendedores = $vendedores;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        try {
            $resumenFormateado = collect();
            
            // Agregar un encabezado para la hoja
            $resumenFormateado->push([
                'nombre_vendedor' => 'RESUMEN GENERAL DE VENTAS',
                'correlativo' => '',
                'tipo_documento' => '',
                'fecha' => '',
                'hora' => '',
                'nombre_cliente' => '',
                'nombre_sucursal' => '',
                'nombre_categoria' => '',
                'nombre_producto' => '',
                'cantidad' => '',
                'precio' => '',
                'descuento' => '',
                'subtotal' => '',
                'total_con_descuento' => '',
                'es_resumen' => true,
                'total_transacciones' => ''
            ]);
            
            // Agregar una línea en blanco
            $resumenFormateado->push($this->emptyRow());
            
            // Para cada vendedor, agregar un resumen de sus ventas
            foreach ($this->vendedores as $vendedor) {
                $ventasVendedor = $this->ventas->where('nombre_vendedor', $vendedor);
                $totalVentas = $ventasVendedor->sum('total_con_descuento');
                $totalProductos = $ventasVendedor->sum('cantidad');
                $totalTransacciones = $ventasVendedor->pluck('correlativo')->unique()->count();
                
                $resumenFormateado->push([
                    'nombre_vendedor' => $vendedor,
                    'correlativo' => '',
                    'tipo_documento' => '',
                    'fecha' => '',
                    'hora' => '',
                    'nombre_cliente' => "Total Productos: {$totalProductos}",
                    'nombre_sucursal' => '',
                    'nombre_categoria' => '',
                    'nombre_producto' => '',
                    'cantidad' => $totalProductos,
                    'precio' => '',
                    'descuento' => '',
                    'subtotal' => '',
                    'total_con_descuento' => $totalVentas,
                    'es_resumen' => true,
                    'total_transacciones' => $totalTransacciones
                ]);
            }
            
            // Agregar una línea en blanco
            $resumenFormateado->push($this->emptyRow());
            
            // Agregar totales generales
            $totalGeneralVentas = $this->ventas->sum('total_con_descuento');
            $totalGeneralProductos = $this->ventas->sum('cantidad');
            $totalGeneralTransacciones = $this->ventas->pluck('correlativo')->unique()->count();
            
            $resumenFormateado->push([
                'nombre_vendedor' => 'TOTAL GENERAL',
                'correlativo' => '',
                'tipo_documento' => '',
                'fecha' => '',
                'hora' => '',
                'nombre_cliente' => '',
                'nombre_sucursal' => '',
                'nombre_categoria' => '',
                'nombre_producto' => '',
                'cantidad' => $totalGeneralProductos,
                'precio' => '',
                'descuento' => '',
                'subtotal' => '',
                'total_con_descuento' => $totalGeneralVentas,
                'es_resumen' => true,
                'total_transacciones' => $totalGeneralTransacciones
            ]);
            
            // Agregar análisis por categoría
            $resumenFormateado->push($this->emptyRow());
            $resumenFormateado->push([
                'nombre_vendedor' => 'VENTAS POR CATEGORÍA',
                'correlativo' => '',
                'tipo_documento' => '',
                'fecha' => '',
                'hora' => '',
                'nombre_cliente' => '',
                'nombre_sucursal' => '',
                'nombre_categoria' => '',
                'nombre_producto' => '',
                'cantidad' => '',
                'precio' => '',
                'descuento' => '',
                'subtotal' => '',
                'total_con_descuento' => '',
                'es_resumen' => true,
                'total_transacciones' => ''
            ]);
            
            // Agrupar ventas por categoría
            $ventasPorCategoria = $this->ventas->groupBy('nombre_categoria');
            
            foreach ($ventasPorCategoria as $categoria => $ventasCategoria) {
                $totalCategoria = $ventasCategoria->sum('total_con_descuento');
                $totalProductosCategoria = $ventasCategoria->sum('cantidad');
                
                $resumenFormateado->push([
                    'nombre_vendedor' => '',
                    'correlativo' => '',
                    'tipo_documento' => '',
                    'fecha' => '',
                    'hora' => '',
                    'nombre_cliente' => '',
                    'nombre_sucursal' => '',
                    'nombre_categoria' => $categoria,
                    'nombre_producto' => '',
                    'cantidad' => $totalProductosCategoria,
                    'precio' => '',
                    'descuento' => '',
                    'subtotal' => '',
                    'total_con_descuento' => $totalCategoria,
                    'es_resumen' => true,
                    'total_transacciones' => ''
                ]);
            }
            
            return $resumenFormateado;
            
        } catch (\Exception $e) {
            Log::error('Error en collection de DetalleVentasResumenSheet', [
                'error' => $e->getMessage()
            ]);
            
            return collect([
                [
                    'nombre_vendedor' => 'Error al generar resumen: ' . $e->getMessage(),
                    'total_con_descuento' => 0
                ]
            ]);
        }
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        return $this->titulo;
    }
    
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Vendedor',
            'Correlativo',
            'Tipo Documento',
            'Fecha',
            'Hora',
            'Cliente',
            'Sucursal',
            'Categoría',
            'Producto',
            'Cantidad',
            'Precio',
            'Descuento',
            'Subtotal',
            'Total',
            'Transacciones'
        ];
    }
    
    /**
     * @param mixed $row
     * @return array
     */
    public function map($fila): array
    {
        try {
            return [
                $fila['nombre_vendedor'], // Vendedor
                $fila['correlativo'], // Correlativo
                $fila['tipo_documento'], // Tipo Documento
                $fila['fecha'], // Fecha
                $fila['hora'], // Hora
                $fila['nombre_cliente'], // Cliente
                $fila['nombre_sucursal'], // Sucursal
                $fila['nombre_categoria'], // Categoría
                $fila['nombre_producto'], // Producto
                is_numeric($fila['cantidad']) ? number_format($fila['cantidad'], 0) : '', // Cantidad
                is_numeric($fila['precio']) ? number_format(round($fila['precio'], 2), 2) : '', // Precio
                is_numeric($fila['descuento']) ? number_format(round($fila['descuento'], 2), 2) : '', // Descuento
                is_numeric($fila['subtotal']) ? number_format(round($fila['subtotal'], 2), 2) : '', // Subtotal
                is_numeric($fila['total_con_descuento']) ? number_format(round($fila['total_con_descuento'], 2), 2) : '', // Total
                $fila['total_transacciones'] // Transacciones
            ];
        } catch (\Exception $e) {
            Log::error('Error en map de DetalleVentasResumenSheet', [
                'error' => $e->getMessage(),
                'fila' => $fila
            ]);
            
            return [
                'Error al formatear: ' . $e->getMessage(), '', '', '', '', '', '', '', '', '', '', '', '', '', ''
            ];
        }
    }
    
    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Obtener el número de la última fila
        $highestRow = $sheet->getHighestRow();
        
        return [
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
            // Estilo para el título del resumen (RESUMEN GENERAL DE VENTAS)
            // 2 => [
            //     'font' => ['bold' => true, 'size' => 14], 
            //     'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F81BD']], 
            //     'font' => ['color' => ['rgb' => 'FFFFFF']]
            // ],
            // Estilo para las filas de totales por vendedor
            // 'A' => ['font' => ['bold' => true]],
            // // Estilo para la fila del TOTAL GENERAL
            // $highestRow - 1 => [
            //     'font' => ['bold' => true], 
            //     'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E0F7FA']]
            // ],
            // Estilo para el título de VENTAS POR CATEGORÍA
            // $highestRow => [
            //     'font' => ['bold' => true], 
            //     'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '9BBB59']], 
            //     'font' => ['color' => ['rgb' => 'FFFFFF']]
            // ],
        ];
    }
    
    /**
     * Crea una fila vacía
     * @return array
     */
    private function emptyRow()
    {
        return [
            'nombre_vendedor' => '',
            'correlativo' => '',
            'tipo_documento' => '',
            'fecha' => '',
            'hora' => '',
            'nombre_cliente' => '',
            'nombre_sucursal' => '',
            'nombre_categoria' => '',
            'nombre_producto' => '',
            'cantidad' => '',
            'precio' => '',
            'descuento' => '',
            'subtotal' => '',
            'total_con_descuento' => '',
            'es_resumen' => false,
            'total_transacciones' => ''
        ];
    }
}