<?php

namespace App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Log;

class DetalleVentasVendedorSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    private $vendedor;
    private $ventas;

    public function __construct($vendedor, $ventas)
    {
        $this->vendedor = $vendedor;
        $this->ventas = $ventas;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        try {
            $ventasFormateadas = collect();
            
            $totalVentas = $this->ventas->sum('total_con_descuento');
            $totalProductos = $this->ventas->sum('cantidad');
            $totalTransacciones = $this->ventas->pluck('correlativo')->unique()->count();
            
            $ventasFormateadas->push([
                'nombre_vendedor' => $this->vendedor,
                'correlativo' => '',
                'tipo_documento' => '',
                'fecha' => '',
                'hora' => '',
                'nombre_cliente' => '',
                'nombre_sucursal' => '',
                'nombre_categoria' => 'RESUMEN',
                'nombre_producto' => "Total Productos: {$totalProductos}",
                'cantidad' => '',
                'precio' => '',
                'descuento' => '',
                'subtotal' => '',
                'total_con_descuento' => $totalVentas,
                'es_resumen' => true,
                'total_transacciones' => $totalTransacciones
            ]);
            
            $ventasFormateadas->push($this->emptyRow());
            
            foreach ($this->ventas as $venta) {
                $ventasFormateadas->push([
                    'nombre_vendedor' => $venta->nombre_vendedor,
                    'correlativo' => $venta->correlativo,
                    'tipo_documento' => $venta->tipo_documento,
                    'fecha' => $venta->fecha,
                    'hora' => $venta->hora,
                    'nombre_cliente' => $venta->nombre_cliente,
                    'nombre_sucursal' => $venta->nombre_sucursal,
                    'nombre_categoria' => $venta->nombre_categoria,
                    'nombre_producto' => $venta->nombre_producto,
                    'cantidad' => $venta->cantidad,
                    'precio' => $venta->precio,
                    'descuento' => $venta->descuento,
                    'subtotal' => $venta->subtotal,
                    'total_con_descuento' => $venta->total_con_descuento,
                    'es_resumen' => false,
                    'total_transacciones' => ''
                ]);
            }
            
            return $ventasFormateadas;
            
        } catch (\Exception $e) {
            Log::error('Error en collection de DetalleVentasVendedorSheet', [
                'error' => $e->getMessage(),
                'vendedor' => $this->vendedor
            ]);
            
            return collect([
                [
                    'nombre_vendedor' => 'Error al generar datos: ' . $e->getMessage(),
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
        // Limitar el título a 31 caracteres (límite de Excel para nombres de hojas)
        return substr($this->vendedor, 0, 31);
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
            // Si es una fila de resumen, aplicar un formato especial
            if (isset($fila['es_resumen']) && $fila['es_resumen']) {
                return [
                    $fila['nombre_vendedor'], // Vendedor
                    '', // Correlativo
                    '', // Tipo Documento
                    '', // Fecha
                    '', // Hora
                    '', // Cliente
                    '', // Sucursal
                    $fila['nombre_categoria'], // Categoría (RESUMEN)
                    $fila['nombre_producto'], // Producto (contiene el total de productos)
                    '', // Cantidad
                    '', // Precio
                    '', // Descuento
                    '', // Subtotal
                    is_numeric($fila['total_con_descuento']) ? number_format(round($fila['total_con_descuento'], 2), 2) : $fila['total_con_descuento'], // Total
                    $fila['total_transacciones'] // Transacciones
                ];
            }
            
            // Para filas normales de detalles
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
            Log::error('Error en map de DetalleVentasVendedorSheet', [
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
        return [
            // Estilo para los encabezados
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']]],
            // Estilo para la fila de resumen (fila 2)
            2 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E0F7FA']]],
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