<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class NotasEstadosFinancierosExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $payload,
        private string $empresa,
    ) {}

    public function title(): string
    {
        return 'Notas EEFF';
    }

    public function array(): array
    {
        return [['']];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $notas = $this->payload['notas'] ?? [];
                ksort($notas);

                $row = 1;
                $sheet->setCellValue('A' . $row, $this->empresa);
                $sheet->mergeCells("A{$row}:C{$row}");
                $row++;
                $sheet->setCellValue('A' . $row, 'NOTAS A LOS ESTADOS FINANCIEROS (NIIF PYMES — El Salvador)');
                $sheet->mergeCells("A{$row}:C{$row}");
                $row++;
                $sheet->setCellValue('A' . $row, ($this->payload['fecha_inicio'] ?? '') . ' al ' . ($this->payload['fecha_fin'] ?? ''));
                $sheet->mergeCells("A{$row}:C{$row}");
                $row += 2;

                $sheet->setCellValue("A{$row}", 'Nota');
                $sheet->setCellValue("B{$row}", 'Título');
                $sheet->setCellValue("C{$row}", 'Estado');
                $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                ]);
                $row++;

                foreach ($notas as $num => $nota) {
                    $sheet->setCellValue('A' . $row, $num);
                    $sheet->setCellValue('B' . $row, $nota['titulo'] ?? '');
                    $sheet->setCellValue('C' . $row, $nota['estado'] ?? '');
                    $row++;
                }

                $row += 2;
                $sheet->setCellValue('A' . $row, 'Validaciones cruzadas');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                foreach ($this->payload['validaciones_cruzadas'] ?? [] as $v) {
                    $sheet->setCellValue('A' . $row, $v['descripcion'] ?? '');
                    $sheet->setCellValue('B' . $row, ($v['cuadra'] ?? false) ? 'OK' : 'Diferencia');
                    $sheet->setCellValue('C' . $row, (float) ($v['diferencia'] ?? 0));
                    $row++;
                }
            },
        ];
    }
}
