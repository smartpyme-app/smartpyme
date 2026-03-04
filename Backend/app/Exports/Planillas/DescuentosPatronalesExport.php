<?php

namespace App\Exports\Planillas;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DescuentosPatronalesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected $planilla;
    protected $detalles;

    public function __construct($planilla, $detalles)
    {
        $this->planilla = $planilla;
        $this->detalles = $detalles;
    }

    public function collection()
    {
        return $this->detalles;
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombres y Apellidos',
            'DUI',
            'NIT',
            'ISSS Empleado',
            'AFP Empleado',
            'Departamento',
            'Cargo',
            'Salario Base',
            'Salario Devengado',
            'ISSS Patronal (7.5%)',
            'AFP Patronal (7.75%)',
            'Total Aportes Patronales',
            'Porcentaje s/Salario',
        ];
    }

    public function map($detalle): array
    {
        $totalPatronal = ($detalle->isss_patronal ?? 0) + ($detalle->afp_patronal ?? 0);
        $porcentajePatronal = $detalle->salario_devengado > 0 ? 
            ($totalPatronal / $detalle->salario_devengado) * 100 : 0;

        return [
            $detalle->empleado_codigo ?? '',
            trim(($detalle->nombres ?? '') . ' ' . ($detalle->apellidos ?? '')),
            $detalle->dui ?? '',
            $detalle->nit ?? '',
            $detalle->empleado_isss ?? '',
            $detalle->empleado_afp ?? '',
            $detalle->departamento_nombre ?? '',
            $detalle->cargo_nombre ?? '',
            round($detalle->salario_base ?? 0, 2),
            round($detalle->salario_devengado ?? 0, 2),
            round($detalle->isss_patronal ?? 0, 2),
            round($detalle->afp_patronal ?? 0, 2),
            round($totalPatronal, 2),
            round($porcentajePatronal, 2) . '%',
        ];
    }

    public function title(): string
    {
        return 'Descuentos Patronales ' . $this->planilla->codigo;
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para encabezados
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '366092']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);

        // Aplicar bordes a todos los datos
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        // Formato numérico para columnas de dinero (I, J, K, L, M)
        $moneyColumns = ['I', 'J', 'K', 'L', 'M'];
        foreach ($moneyColumns as $col) {
            if ($col !== 'N') { // N es porcentaje
                $sheet->getStyle($col . '2:' . $col . $lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
            }
        }

        // Ajustar altura de la fila de encabezados
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }
}