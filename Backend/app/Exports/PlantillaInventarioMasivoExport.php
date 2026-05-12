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
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PlantillaInventarioMasivoExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithColumnFormatting, WithCustomValueBinder, WithEvents
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
            ->orderBy('nombre', 'asc');

        // Plantilla vacía: solo encabezados Excel, sin filas de productos.
        if (!empty($this->filtros['plantilla_vacia'])) {
            return $query->whereRaw('0 = 1');
        }

        $ids = $this->filtros['productos_ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];

        // Sin IDs: todos los productos de la empresa (p. ej. GET sin plantilla_vacia ni productos_ids).
        // Con IDs: solo esos productos.
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

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

  
        $stockOrigen = $inventarioOrigen ? $inventarioOrigen->stock : 0;
        $idBodegaOrigen = $inventarioOrigen ? $inventarioOrigen->id_bodega : '';
        $nombreBodegaOrigen = $inventarioOrigen ? $inventarioOrigen->nombre_bodega : 'N/A';


        $stockDestino = $inventarioDestino ? $inventarioDestino->stock : 0;
        $idBodegaDestino = $inventarioDestino ? $inventarioDestino->id_bodega : '';
        $nombreBodegaDestino = $inventarioDestino ? $inventarioDestino->nombre_bodega : 'N/A';

        return [
            $producto->id,
            $idBodegaOrigen,
            $idBodegaDestino,
            $producto->codigo !== null ? (string) $producto->codigo : 'N/A',
            $producto->nombre,
            $producto->categoria ? $producto->categoria->nombre : 'N/A',
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
            'D' => ['numberFormat' => ['formatCode' => NumberFormat::FORMAT_TEXT]],
            'K' => ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']]],
            'A1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
            'B1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
            'C1' => ['font' => ['color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
        ];
    }

    public function title(): string
    {
        return 'Traslado de Inventario';
    }

    public function bindValue(Cell $cell, $value)
    {
        try {
            [$col, $row] = Coordinate::coordinateFromString($cell->getCoordinate());
        } catch (\Throwable $e) {
            return parent::bindValue($cell, $value);
        }

        $row = (int) $row;
        if ($col === 'D' && $row > 1) {
            if ($value === null || $value === '') {
                return parent::bindValue($cell, $value);
            }
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            $cell->getWorksheet()
                ->getStyle($cell->getCoordinate())
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, (int) $sheet->getHighestRow());
                for ($r = 1; $r <= $lastRow; $r++) {
                    $sheet->getStyle('D' . $r)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            },
        ];
    }
}
