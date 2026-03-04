<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PartidaExcelExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, ShouldAutoSize
{
    protected $partida;

    public function __construct($partida)
    {
        $this->partida = $partida->load('detalles');
    }

    public function collection()
    {
        return $this->partida->detalles;
    }

    public function headings(): array
    {
        return [
            'Cuenta',
            'Concepto',
            'Debe',
            'Haber'
        ];
    }

    public function map($detalle): array
    {
        $cuenta = ($detalle->codigo ?? '') . ' - ' . ($detalle->nombre_cuenta ?? '');
        return [
            $cuenta,
            $detalle->concepto ?? '',
            $detalle->debe ? (float) $detalle->debe : 0,
            $detalle->haber ? (float) $detalle->haber : 0
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'C:D' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $totalsRow = $lastRow + 1;

                $totalDebe = $this->partida->detalles->sum('debe');
                $totalHaber = $this->partida->detalles->sum('haber');

                $sheet->setCellValue('A' . $totalsRow, '');
                $sheet->setCellValue('B' . $totalsRow, 'Totales:');
                $sheet->setCellValue('C' . $totalsRow, $totalDebe);
                $sheet->setCellValue('D' . $totalsRow, $totalHaber);

                $sheet->getStyle('B' . $totalsRow . ':D' . $totalsRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
            },
        ];
    }
}
