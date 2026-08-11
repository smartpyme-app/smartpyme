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

class ReporteCostoPatronalExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
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
            'Colaborador',
            'Departamento',
            'Cargo',
            'Salario Bruto',
            'CCSS Patronal (26.83%)',
            'INS Riesgos de Trabajo (1.5%)',
            'Total Cargas Patronales',
            'Costo Total Empresa',
            '% Carga Patronal s/Bruto',
        ];
    }

    public function map($detalle): array
    {
        $salarioBruto = $detalle->total_ingresos ?? $detalle->salario_devengado ?? 0;
        
        $cargas = CostaRicaCargasSocialesHelper::calcularCargasSociales(
            $salarioBruto,
            $this->planilla->tipo_planilla ?? 'mensual'
        );

        $ccssPatronal = $cargas['ccss_patronal'];
        $insPatronal = $cargas['ins_patronal'];
        $totalCargasPatronales = $ccssPatronal + $insPatronal;
        $costoTotalEmpresa = $salarioBruto + $totalCargasPatronales;

        $porcentajeCarga = $salarioBruto > 0 ? ($totalCargasPatronales / $salarioBruto) * 100 : 0;

        $empleado = $detalle->empleado;
        $nombreCompleto = trim(($empleado->nombres ?? '') . ' ' . ($empleado->apellidos ?? ''));

        return [
            $empleado->codigo ?? '',
            $nombreCompleto,
            $empleado->departamento->nombre ?? '',
            $empleado->cargo->nombre ?? '',
            round($salarioBruto, 2),
            round($ccssPatronal, 2),
            round($insPatronal, 2),
            round($totalCargasPatronales, 2),
            round($costoTotalEmpresa, 2),
            round($porcentajeCarga, 2) . '%',
        ];
    }

    public function title(): string
    {
        return 'Costo Patronal ' . $this->planilla->codigo;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
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
        $sheet->getStyle("E2:I{$lastRow}")->getNumberFormat()->setFormatCode($this->moneyFormat);

        return [];
    }
}
