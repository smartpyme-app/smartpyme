<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BalanceGeneralExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /** @var array<string, mixed> */
    protected array $balance;

    protected string $nombreEmpresa;

    public function __construct(array $balance, string $nombreEmpresa)
    {
        $this->balance = $balance;
        $this->nombreEmpresa = $nombreEmpresa;
    }

    public function title(): string
    {
        return 'Balance General';
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
                $sheet->setCellValue('A1', $this->nombreEmpresa);
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A2', 'BALANCE GENERAL — Estado de situación financiera (NIIF PYMES, El Salvador)');
                $sheet->mergeCells('A2:C2');
                $sheet->setCellValue('A3', 'Al ' . ($this->balance['fecha_corte_label'] ?? ''));
                $sheet->mergeCells('A3:C3');
                $sheet->setCellValue('A4', 'Expresado en dólares estadounidenses (USD)');
                $sheet->mergeCells('A4:C4');
                if (! empty($this->balance['nota_metodologia'])) {
                    $sheet->setCellValue('A5', $this->balance['nota_metodologia']);
                    $sheet->mergeCells('A5:C5');
                }

                $row = 7;
                $sheet->setCellValue('A' . $row, 'Concepto');
                $sheet->setCellValue('B' . $row, 'Clave');
                $sheet->setCellValue('C' . $row, 'Importe');
                $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2E8F0'],
                    ],
                ]);
                $row++;

                $formulaRanges = [];

                $row = $this->writeBlock($sheet, $row, 'ACTIVOS', $this->balance['activo_corriente'] ?? [], $formulaRanges);
                $row = $this->writeBlock($sheet, $row, '', $this->balance['activo_no_corriente'] ?? [], $formulaRanges);

                $sheet->setCellValue('A' . $row, 'TOTAL ACTIVOS');
                $sheet->setCellValue('B' . $row, '');
                $tAct = $this->balance['totales']['activos'] ?? 0;
                $r1 = $formulaRanges['activo_corriente_total'] ?? null;
                $r2 = $formulaRanges['activo_no_corriente_total'] ?? null;
                if ($r1 && $r2) {
                    $sheet->setCellValue('C' . $row, '=C' . $r1 . '+C' . $r2);
                } else {
                    $sheet->setCellValue('C' . $row, $tAct);
                }
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('C' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $formulaRanges['total_activos_row'] = $row;
                $row += 2;

                $row = $this->writeBlock($sheet, $row, 'PASIVOS', $this->balance['pasivo_corriente'] ?? [], $formulaRanges);
                $row = $this->writeBlock($sheet, $row, '', $this->balance['pasivo_no_corriente'] ?? [], $formulaRanges);

                $sheet->setCellValue('A' . $row, 'TOTAL PASIVOS');
                $sheet->setCellValue('B' . $row, '');
                $p1 = $formulaRanges['pasivo_corriente_total'] ?? null;
                $p2 = $formulaRanges['pasivo_no_corriente_total'] ?? null;
                if ($p1 && $p2) {
                    $sheet->setCellValue('C' . $row, '=C' . $p1 . '+C' . $p2);
                } else {
                    $sheet->setCellValue('C' . $row, $this->balance['totales']['pasivos'] ?? 0);
                }
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('C' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $formulaRanges['total_pasivos_row'] = $row;
                $row += 2;

                $row = $this->writeBlock($sheet, $row, 'PATRIMONIO', $this->balance['patrimonio'] ?? [], $formulaRanges);

                $sheet->setCellValue('A' . $row, 'TOTAL PASIVOS + PATRIMONIO');
                $sheet->setCellValue('B' . $row, '');
                $tp = $formulaRanges['total_pasivos_row'] ?? null;
                $tPat = $formulaRanges['patrimonio_total'] ?? null;
                if ($tp && $tPat) {
                    $sheet->setCellValue('C' . $row, '=C' . $tp . '+C' . $tPat);
                } else {
                    $sheet->setCellValue('C' . $row, $this->balance['totales']['pasivos_mas_patrimonio'] ?? 0);
                }
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $sheet->getStyle('C' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                $sheet->getStyle('C' . $row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

                $row += 2;
                $ok = $this->balance['ecuacion_cuadra'] ?? false;
                $sheet->setCellValue('A' . $row, $ok
                    ? 'Ecuación contable: Activos = Pasivos + Patrimonio (verificado).'
                    : 'Diferencia en ecuación contable: revisar catálogo y partidas.');
                $sheet->mergeCells('A' . $row . ':C' . $row);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if (! $ok) {
                    $sheet->getStyle('A' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FDE2E2');
                }

                $sheet->getStyle('A1:C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C8:C' . ($row + 5))->getNumberFormat()->setFormatCode('#,##0.00');
            },
        ];
    }

    /**
     * @param  array<string, int|null>  $formulaRanges
     */
    private function writeBlock(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, string $sectionTitle, array $block, array &$formulaRanges): int
    {
        if ($sectionTitle !== '') {
            $sheet->setCellValue('A' . $row, $sectionTitle);
            $sheet->mergeCells('A' . $row . ':C' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;
        }

        if (empty($block['lineas'])) {
            return $row;
        }

        $sheet->setCellValue('A' . $row, $block['titulo'] ?? '');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true);
        $row++;

        $firstDataRow = $row;
        foreach ($block['lineas'] as $linea) {
            $sheet->setCellValue('A' . $row, $linea['etiqueta'] ?? '');
            $sheet->setCellValue('B' . $row, $linea['clave'] ?? '');
            $sheet->setCellValue('C' . $row, (float) ($linea['monto'] ?? 0));
            $row++;
        }

        $lastDataRow = $row - 1;
        $sheet->setCellValue('A' . $row, $block['total_etiqueta'] ?? 'TOTAL');
        $sheet->setCellValue('B' . $row, '');
        if ($lastDataRow >= $firstDataRow) {
            $sheet->setCellValue('C' . $row, '=SUM(C' . $firstDataRow . ':C' . $lastDataRow . ')');
        } else {
            $sheet->setCellValue('C' . $row, 0);
        }
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $sheet->getStyle('C' . $row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

        $slug = $this->blockTotalKey($block['titulo'] ?? '');
        if ($slug !== '') {
            $formulaRanges[$slug] = $row;
        }
        $row++;

        return $row;
    }

    private function blockTotalKey(string $titulo): string
    {
        $t = mb_strtolower($titulo, 'UTF-8');
        if (strpos($t, 'activo corriente') !== false) {
            return 'activo_corriente_total';
        }
        if (strpos($t, 'activo no corriente') !== false) {
            return 'activo_no_corriente_total';
        }
        if (strpos($t, 'pasivo corriente') !== false) {
            return 'pasivo_corriente_total';
        }
        if (strpos($t, 'pasivo no corriente') !== false) {
            return 'pasivo_no_corriente_total';
        }
        if (strpos($t, 'patrimonio') !== false) {
            return 'patrimonio_total';
        }

        return '';
    }
}
