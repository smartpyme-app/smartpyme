<?php

namespace App\Exports\Contabilidad;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LibroIvaResumenFiscalExport implements FromArray, WithEvents, WithTitle
{
    private const LAST_COL = 'D';

    private const FILL_SECTION = 'E2E8F0';

    private const FILL_HEADER = 'F1F3F5';

    private const FILL_TOTAL = 'DEE2E6';

    /** @var array<string, mixed> */
    protected array $resumen;

    protected string $nombreEmpresa;

    public function __construct(array $resumen, string $nombreEmpresa)
    {
        $this->resumen = $resumen;
        $this->nombreEmpresa = $nombreEmpresa;
    }

    public function title(): string
    {
        return 'Resumen fiscal';
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
                $lastCol = self::LAST_COL;

                $sheet->getColumnDimension('A')->setWidth(42);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(18);

                $periodo = $this->resumen['periodo'] ?? [];
                $inicio = $periodo['inicio'] ?? null;
                $fin = $periodo['fin'] ?? null;
                $periodoLabel = $inicio && $fin
                    ? ucfirst(Carbon::parse($inicio)->translatedFormat('F Y'))
                        .' ('.Carbon::parse($inicio)->format('d/m/Y').' - '.Carbon::parse($fin)->format('d/m/Y').')'
                    : '';

                $row = 1;
                $this->writeMergedTitle($sheet, $row, 'RESUMEN FISCAL', 16, true);
                $row++;
                $this->writeMergedTitle($sheet, $row, $this->nombreEmpresa, 12, true);
                $row++;
                if (! empty($this->resumen['pais'])) {
                    $sheet->setCellValue('A'.$row, 'País');
                    $sheet->setCellValue('B'.$row, (string) $this->resumen['pais']);
                    $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
                    $this->styleMetaRow($sheet, $row, $lastCol);
                    $row++;
                }
                $sheet->setCellValue('A'.$row, 'Período');
                $sheet->setCellValue('B'.$row, $periodoLabel);
                $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
                $this->styleMetaRow($sheet, $row, $lastCol);
                $row++;
                $row = $this->blankRow($sheet, $row, 10);

                $totales = $this->resumen['totales'] ?? [];
                $row = $this->writeSectionTitle($sheet, $row, $lastCol, 'Resumen del periodo');
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Total ventas', (float) ($totales['ventas'] ?? 0));
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Total compras', (float) ($totales['compras'] ?? 0));
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Total gastos', (float) ($totales['gastos'] ?? 0));
                $row = $this->blankRow($sheet, $row, 10);

                $row = $this->writeDesgloseTable(
                    $sheet,
                    $row,
                    $lastCol,
                    'Compras por impuesto',
                    $this->resumen['compras_por_impuesto'] ?? []
                );
                $row = $this->writeDesgloseTable(
                    $sheet,
                    $row,
                    $lastCol,
                    'Ventas por impuesto',
                    $this->resumen['ventas_por_impuesto'] ?? []
                );

                $iva = $this->resumen['iva'] ?? [];
                $row = $this->writeSectionTitle($sheet, $row, $lastCol, 'Resumen de impuestos');
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Crédito', (float) ($iva['iva_a_favor'] ?? 0));
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Débito', (float) ($iva['iva_en_contra'] ?? 0));
                $row = $this->writeLabelValue($sheet, $row, $lastCol, 'Diferencia', (float) ($iva['diferencia_estimada_pago_iva'] ?? 0), true);

                $pago = $this->resumen['pago_a_cuenta_iva'] ?? [];
                if (! empty($pago['aplica'])) {
                    $row = $this->blankRow($sheet, $row, 10);
                    $row = $this->writeSectionTitle($sheet, $row, $lastCol, 'Pago a cuenta (impuesto)');
                    $sheet->setCellValue('A'.$row, (float) ($pago['monto'] ?? 0));
                    $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                    $this->applyMoneyFormat($sheet, "A{$row}:{$lastCol}{$row}");
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(24);
                    $row++;
                    $descripcion = trim((string) ($pago['descripcion'] ?? ''));
                    if ($descripcion !== '') {
                        $sheet->setCellValue('A'.$row, $descripcion);
                        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                            'font' => ['color' => ['rgb' => '6C757D'], 'size' => 9],
                            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        ]);
                        $sheet->getRowDimension($row)->setRowHeight(28);
                    }
                }
            },
        ];
    }

    private function writeMergedTitle($sheet, int $row, string $text, int $fontSize, bool $bold): int
    {
        $lastCol = self::LAST_COL;
        $sheet->setCellValue('A'.$row, $text);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => $bold, 'size' => $fontSize],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight($fontSize >= 14 ? 28 : 22);

        return $row + 1;
    }

    private function styleMetaRow($sheet, int $row, string $lastCol): void
    {
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function blankRow($sheet, int $row, int $height): int
    {
        $sheet->getRowDimension($row)->setRowHeight($height);

        return $row + 1;
    }

    private function writeSectionTitle($sheet, int $row, string $lastCol, string $title): int
    {
        $sheet->setCellValue('A'.$row, $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::FILL_SECTION],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);

        return $row + 1;
    }

    private function writeLabelValue($sheet, int $row, string $lastCol, string $label, float $value, bool $bold = false): int
    {
        $sheet->setCellValue('A'.$row, $label);
        $sheet->setCellValue('B'.$row, $value);
        $sheet->mergeCells("B{$row}:{$lastCol}{$row}");
        $this->applyMoneyFormat($sheet, "B{$row}:{$lastCol}{$row}");
        $style = [
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle("B{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if ($bold) {
            $style['font'] = ['bold' => true];
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray($style);
        $sheet->getRowDimension($row)->setRowHeight(22);

        return $row + 1;
    }

    /**
     * @param  array<int, array{tarifa?: string, etiqueta?: string, base?: float, iva?: float}>  $desglose
     */
    private function writeDesgloseTable($sheet, int $row, string $lastCol, string $titulo, array $desglose): int
    {
        if ($desglose === []) {
            return $row;
        }

        $row = $this->writeSectionTitle($sheet, $row, $lastCol, $titulo);

        $headerRow = $row;
        $sheet->setCellValue('A'.$headerRow, 'Concepto');
        $sheet->setCellValue('B'.$headerRow, 'Base');
        $sheet->setCellValue('C'.$headerRow, 'Impuesto');
        $sheet->setCellValue('D'.$headerRow, 'Total');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::FILL_HEADER],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'ADB5BD']],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $row++;

        $firstDataRow = $row;
        $sumaBase = 0.0;
        $sumaIva = 0.0;
        foreach ($desglose as $item) {
            $base = (float) ($item['base'] ?? 0);
            $iva = (float) ($item['iva'] ?? 0);
            $sumaBase += $base;
            $sumaIva += $iva;
            $sheet->setCellValue('A'.$row, (string) ($item['etiqueta'] ?? ''));
            $sheet->setCellValue('B'.$row, $base);
            $sheet->setCellValue('C'.$row, $iva);
            $sheet->setCellValue('D'.$row, round($base + $iva, 2));
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue('A'.$totalRow, 'Total');
        $sheet->setCellValue('B'.$totalRow, round($sumaBase, 2));
        $sheet->setCellValue('C'.$totalRow, round($sumaIva, 2));
        $sheet->setCellValue('D'.$totalRow, round($sumaBase + $sumaIva, 2));
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::FILL_TOTAL],
            ],
        ]);
        $sheet->getRowDimension($totalRow)->setRowHeight(22);
        $row++;

        $this->applyMoneyFormat($sheet, "B{$firstDataRow}:D{$totalRow}");
        $sheet->getStyle("B{$firstDataRow}:D{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DEE2E6'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$firstDataRow}:A{$totalRow}")->getAlignment()->setWrapText(true);

        return $this->blankRow($sheet, $row, 12);
    }

    private function applyMoneyFormat($sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
    }
}
