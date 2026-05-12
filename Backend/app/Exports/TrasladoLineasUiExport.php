<?php

namespace App\Exports;

use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de traslado a partir del listado en pantalla (stocks, bodegas y cantidades por fila).
 */
class TrasladoLineasUiExport extends DefaultValueBinder implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle, WithColumnFormatting, WithCustomValueBinder, WithEvents
{
    /** @var array<int, array<int, mixed>> */
    protected array $rows;

    public function __construct(array $lineas)
    {
        $parsed = [];
        foreach ($lineas as $r) {
            if (!is_array($r)) {
                continue;
            }
            $idProducto = isset($r['id_producto']) ? (int) $r['id_producto'] : 0;
            if ($idProducto <= 0) {
                continue;
            }
            $parsed[] = $r;
        }

        $idsSinCodigo = [];
        foreach ($parsed as $r) {
            $cod = isset($r['codigo']) ? trim((string) $r['codigo']) : '';
            if ($cod === '' && isset($r['id_producto'])) {
                $idsSinCodigo[] = (int) $r['id_producto'];
            }
        }
        $idsSinCodigo = array_values(array_unique(array_filter($idsSinCodigo)));
        $codigosPorId = [];
        if ($idsSinCodigo !== []) {
            $codigosPorId = Producto::query()
                ->whereIn('id', $idsSinCodigo)
                ->pluck('codigo', 'id')
                ->all();
        }

        $this->rows = [];
        foreach ($parsed as $r) {
            $idProducto = (int) $r['id_producto'];
            $codigo = isset($r['codigo']) ? trim((string) $r['codigo']) : '';
            if ($codigo === '') {
                $codigo = isset($codigosPorId[$idProducto]) && (string) $codigosPorId[$idProducto] !== ''
                    ? (string) $codigosPorId[$idProducto]
                    : 'N/A';
            }

            $idBo = $r['id_bodega_origen'] ?? null;
            $idBd = $r['id_bodega_destino'] ?? null;

            $this->rows[] = [
                $idProducto,
                $idBo !== null && $idBo !== '' ? (int) $idBo : '',
                $idBd !== null && $idBd !== '' ? (int) $idBd : '',
                $codigo,
                (string) ($r['nombre'] ?? ''),
                isset($r['nombre_categoria']) && (string) $r['nombre_categoria'] !== '' ? (string) $r['nombre_categoria'] : 'N/A',
                (string) ($r['nombre_bodega_origen'] ?? 'N/A'),
                (string) ($r['nombre_bodega_destino'] ?? 'N/A'),
                (float) ($r['stock_origen'] ?? 0),
                (float) ($r['stock_destino'] ?? 0),
                (float) ($r['cantidad_traslado'] ?? 0),
            ];
        }
    }

    public function array(): array
    {
        return $this->rows;
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
            'Bodega Origen',
            'Bodega Destino',
            'Stock Origen',
            'Stock Destino',
            'Cantidad a Trasladar',
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
