<?php

namespace App\Exports;

use App\Helpers\CurrencyHelper;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanillaExportTemplate implements FromArray, WithHeadings, WithStyles, WithColumnFormatting
{
    protected $headers;
    protected $data;

    public function __construct(array $headers, array $data)
    {
        $this->headers = $headers;
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return array_values($this->headers);
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 1;
        
        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ]
            ],
        ];
    }

    public function columnFormats(): array
    {
        $moneyFormat = CurrencyHelper::excelFormat();
        $moneyColumns = ['C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'T'];
        $formats = [];

        foreach ($moneyColumns as $column) {
            $formats[$column] = $moneyFormat;
        }

        return $formats;
    }
}