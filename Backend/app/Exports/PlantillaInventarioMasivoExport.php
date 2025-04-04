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

class PlantillaInventarioMasivoExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithColumnFormatting
{
    protected $filtros;

    public function __construct(array $filtros)
    {
        $this->filtros = $filtros;
    }

    public function query()
    {
        $query = Producto::query()
            ->with(['inventarios', 'categoria', 'composiciones'])
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
            '#ID_PRODUCTO',
            '#ID_BODEGA_ORIGEN',
            '#ID_BODEGA_DESTINO',
            'Código',
            'Producto',
            'Categoría',
          //  'Tiene Composiciones',
            'Bodega Origen',
            'Bodega Destino',
            'Stock Origen',
            'Stock Destino',
            'Cantidad a Trasladar',
        ];
    }

    public function map($producto): array
    {
        $inventarioOrigen = null;
        $inventarioDestino = null;

        if (!empty($this->filtros['id_bodega_origen'])) {
            $inventarioOrigen = $producto->inventarios->first(function ($inv) {
                return $inv->id_bodega == $this->filtros['id_bodega_origen'];
            });
        }

        if (!empty($this->filtros['id_bodega_destino'])) {
            $inventarioDestino = $producto->inventarios->first(function ($inv) {
                return $inv->id_bodega == $this->filtros['id_bodega_destino'];
            });
        }

        // Stock en bodega origen
        $stockOrigen = $inventarioOrigen ? $inventarioOrigen->stock : 0;
        $idBodegaOrigen = $inventarioOrigen ? $inventarioOrigen->id_bodega : '';
        $nombreBodegaOrigen = $inventarioOrigen ? $inventarioOrigen->nombre_bodega : 'N/A';

        // Stock en bodega destino
        $stockDestino = $inventarioDestino ? $inventarioDestino->stock : 0;
        $idBodegaDestino = $inventarioDestino ? $inventarioDestino->id_bodega : '';
        $nombreBodegaDestino = $inventarioDestino ? $inventarioDestino->nombre_bodega : 'N/A';

        // Verificar si el producto tiene composiciones
      //  $tieneComposiciones = count($producto->composiciones) > 0 ? 'Sí' : 'No';

        return [
            $producto->id,
            $idBodegaOrigen,
            $idBodegaDestino,
            $producto->codigo ?? 'N/A',
            $producto->nombre,
            $producto->categoria ? $producto->categoria->nombre : 'N/A',
          //  $tieneComposiciones,
            $nombreBodegaOrigen,
            $nombreBodegaDestino,
            $stockOrigen,
            $stockDestino,
            0, // Cantidad a trasladar (inicialmente en cero)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setVisible(false);
        $sheet->getColumnDimension('B')->setVisible(false);
        $sheet->getColumnDimension('C')->setVisible(false);

        return [
            1 => ['font' => ['bold' => true, 'size' => 12], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]],
            'K' => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']]],
            'A1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
            'B1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
            'C1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Traslado de Inventario';
    }
}