<?php

namespace App\Exports\Planillas\CostaRica;

use App\Helpers\CostaRicaCargasSocialesHelper;
use App\Helpers\CurrencyHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteCCSSExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected $planilla;
    protected $detalles;
    protected $moneyFormat;

    public function __construct($planilla, $detalles)
    {
        $this->planilla = $planilla->loadMissing('empresa.currency');
        $this->detalles = $detalles;
        $this->moneyFormat = CurrencyHelper::excelFormat($this->planilla->empresa);
    }

    public function collection()
    {
        return $this->detalles;
    }

    public function headings(): array
    {
        return [
            'Código',
            'Identificación (Cédula/DIMEX)',
            'Colaborador',
            'Departamento',
            'Cargo',
            'Salario Bruto',
            'Base SEM',
            'Base IVM',
            'CCSS Empleado (10.83%)',
            'CCSS Patronal (26.83%)',
            'Total Aportes CCSS',
        ];
    }

    public function map($detalle): array
    {
        $salarioBruto = $detalle->total_ingresos ?? $detalle->salario_devengado ?? 0;
        
        $cargas = CostaRicaCargasSocialesHelper::calcularCargasSociales(
            $salarioBruto,
            $this->planilla->tipo_planilla ?? 'mensual'
        );

        $identificacion = $detalle->empleado->dui ?? $detalle->empleado->nit ?? '';
        $nombreCompleto = trim(($detalle->empleado->nombres ?? '') . ' ' . ($detalle->empleado->apellidos ?? ''));

        return [
            $detalle->empleado->codigo ?? '',
            $identificacion,
            $nombreCompleto,
            $detalle->empleado->departamento->nombre ?? '',
            $detalle->empleado->cargo->nombre ?? '',
            round($salarioBruto, 2),
            round($cargas['bases_aplicadas']['base_sem_periodo'] ?? $salarioBruto, 2),
            round($cargas['bases_aplicadas']['base_ivm_periodo'] ?? $salarioBruto, 2),
            round($cargas['ccss_empleado'], 2),
            round($cargas['ccss_patronal'], 2),
            round($cargas['ccss_empleado'] + $cargas['ccss_patronal'], 2),
        ];
    }

    public function title(): string
    {
        return 'Reporte CCSS ' . $this->planilla->codigo;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F497D']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        $lastRow = count($this->detalles) + 1;
        $sheet->getStyle("F2:K{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode($this->moneyFormat);

        return [];
    }
}
