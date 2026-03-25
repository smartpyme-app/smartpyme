<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\DB;

class KardexMasivoExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithChunkReading
{
    protected $idEmpresa;

    public function __construct($idEmpresa)
    {
        $this->idEmpresa = $idEmpresa;
    }

    public function query()
    {
        // Obtener IDs de productos de la empresa
        $productoIds = Producto::where('id_empresa', $this->idEmpresa)
            ->where('tipo', 'Producto')
            ->pluck('id');
        
        return Kardex::whereIn('id_producto', $productoIds)
            ->with([
                'producto:id,nombre,codigo',
                'inventario.bodega:id,nombre',
                'inventario.sucursal:id,nombre',
                'usuario:id,nombre'
            ])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');
    }

    public function chunkSize(): int
    {
        return 1000; // Procesar de 1000 en 1000 registros
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Producto',
            'Bodega',
            'Sucursal',
            'Tipo de Movimiento',
            'Detalle',
            'Cantidad',
            'Stock Anterior',
            'Stock Nuevo',
            'Usuario',
            'Referencia'
        ];
    }

    public function map($kardex): array
    {
        return [
            $kardex->fecha ? \Carbon\Carbon::parse($kardex->fecha)->format('d/m/Y H:i:s') : '',
            $kardex->producto ? $kardex->producto->nombre : '',
            $kardex->inventario && $kardex->inventario->bodega ? $kardex->inventario->bodega->nombre : '',
            $kardex->inventario && $kardex->inventario->sucursal ? $kardex->inventario->sucursal->nombre : '',
            $this->getTipoMovimiento($kardex->tipo),
            $kardex->detalle ?? '',
            number_format($kardex->cantidad, 2),
            number_format($kardex->stock_anterior, 2),
            number_format($kardex->stock_nuevo, 2),
            $kardex->usuario ? $kardex->usuario->nombre : '',
            $kardex->referencia ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Fecha
            'B' => 30, // Producto
            'C' => 20, // Bodega
            'D' => 20, // Sucursal
            'E' => 20, // Tipo de Movimiento
            'F' => 30, // Detalle
            'G' => 12, // Cantidad
            'H' => 12, // Stock Anterior
            'I' => 12, // Stock Nuevo
            'J' => 20, // Usuario
            'K' => 15, // Referencia
        ];
    }

    public function title(): string
    {
        return 'Kardex Completo';
    }

    private function getTipoMovimiento($tipo)
    {
        $tipos = [
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'ajuste' => 'Ajuste',
            'traslado_entrada' => 'Traslado Entrada',
            'traslado_salida' => 'Traslado Salida',
            'compra' => 'Compra',
            'venta' => 'Venta',
            'devolucion_compra' => 'Devolución Compra',
            'devolucion_venta' => 'Devolución Venta',
            'consigna_entrada' => 'Consigna Entrada',
            'consigna_salida' => 'Consigna Salida'
        ];

        return $tipos[$tipo] ?? ucfirst($tipo);
    }
}

