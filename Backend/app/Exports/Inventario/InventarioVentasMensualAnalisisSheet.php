<?php

namespace App\Exports\Inventario;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventarioVentasMensualAnalisisSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param array<int, string> $headings
     * @param array<int, array<int, mixed>> $rows
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet)
    {
        $ncol = max(count($this->headings), 1);
        $lastColLetter = Coordinate::stringFromColumnIndex($ncol);
        $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => 'F2F2F2'],
            ],
        ]);

        $lastRow = max($sheet->getHighestRow(), 1);
        if ($lastRow > 50000) {
            $lastRow = 50000;
        }
        $sheet->getStyle('A1:' . $lastColLetter . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        return [];
    }
}
