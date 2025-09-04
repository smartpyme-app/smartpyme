<?php

namespace App\Exports;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PlantillaInventarioExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithColumnFormatting
{
    protected $filtros;

    public function __construct(array $filtros)
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
  
        $query = Producto::query()
            ->with(['inventarios', 'categoria'])
            ->when($this->filtros['id_categoria'] ?? null, function ($q, $id_categoria) {
                return $q->where('id_categoria', $id_categoria);
            })
            ->when($this->filtros['buscador'] ?? null, function ($q, $buscador) {
                return $q->where(function ($q) use ($buscador) {
                    $q->where('nombre', 'like', "%{$buscador}%")
                        ->orWhere('codigo', 'like', "%{$buscador}%")
                        ->orWhere('barcode', 'like', "%{$buscador}%");
                });
            })
            ->orderBy('nombre', 'asc');

        return $query;
    }
    
    public function headings(): array
    {
        return [
            '#ID',        
            '#ID_BODEGA', 
            'Código',
            'Producto',
            'Categoría',
            'Bodega',
            'Stock Actual',
            'Stock Nuevo', 
        ];
    }

    public function map($producto): array
    {

        $inventario = null;
        if (!empty($this->filtros['id_bodega'])) {
            $inventario = $producto->inventarios->first(function ($inv) {
                return $inv->id_bodega == $this->filtros['id_bodega'];
            });
        } else {
 
            $inventario = $producto->inventarios->first();
        }

        // Stock actual
        $stockActual = $inventario ? $inventario->stock : 0;
        $idBodega = $inventario ? $inventario->id_bodega : '';

        return [
            $producto->id,        
            $idBodega,           
            $producto->codigo ?? 'N/A',
            $producto->nombre,
            $producto->categoria ? $producto->categoria->nombre : 'N/A',
            $inventario ? $inventario->nombre_bodega : 'N/A',
            $stockActual,
            $stockActual, // Mismo valor inicial que el stock actual
        ];
    }

    public function styles(Worksheet $sheet)
    {

        //$sheet->getColumnDimension('A')->setVisible(false);
        //$sheet->getColumnDimension('B')->setVisible(false);
        
        return [
    
            1 => ['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]],

        
            'H' => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']]],
            
   
            'A1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
            'B1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Ajuste de Inventario';
    }
}