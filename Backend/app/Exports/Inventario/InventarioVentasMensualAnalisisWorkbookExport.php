<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InventarioVentasMensualAnalisisWorkbookExport implements WithMultipleSheets
{
    /**
     * @param array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}> $sheets
     */
    public function __construct(
        private array $sheets,
    ) {
    }

    public function sheets(): array
    {
        $out = [];
        foreach ($this->sheets as $sheet) {
            $out[] = new InventarioVentasMensualAnalisisSheet(
                $sheet['title'],
                $sheet['headings'],
                $sheet['rows'],
            );
        }

        return $out;
    }
}
