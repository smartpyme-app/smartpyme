<?php

namespace App\Exports\Planillas\CostaRica;

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

class ReporteD138Export implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
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
            'Código Empleado',
            'Identificación (Cédula/DIMEX)',
            'Nombre Completo',
            'Tipo de Contrato',
            'Salario Bruto',
            'Hijos Dependientes',
            'Cónyuge Dependiente',
            'Créditos Fiscales Aplicados',
            'Monto Retención Renta (D-138)',
        ];
    }

    public function map($detalle): array
    {
        $salarioBruto = $detalle->total_ingresos ?? $detalle->salario_devengado ?? 0;
        $empleado = $detalle->empleado;
        $hijos = (int) ($empleado->cantidad_hijos_dependientes ?? 0);
        $conyuge = (bool) ($empleado->tiene_conyuge_dependiente ?? false);

        // Créditos mensuales (₡1,710/hijo, ₡2,590/cónyuge) ajustados al período
        $factor = ($this->planilla->tipo_planilla === 'quincenal') ? 0.5 : 1.0;
        $creditosAplicados = (($hijos * 1710.0) + ($conyuge ? 2590.0 : 0.0)) * $factor;

        $identificacion = $empleado->dui ?? $empleado->nit ?? '';
        $nombreCompleto = trim(($empleado->nombres ?? '') . ' ' . ($empleado->apellidos ?? ''));

        return [
            $empleado->codigo ?? '',
            $identificacion,
            $nombreCompleto,
            $empleado->tipo_contrato ?? 'Permanente',
            round($salarioBruto, 2),
            $hijos,
            $conyuge ? 'SI' : 'NO',
            round($creditosAplicados, 2),
            round($detalle->renta ?? 0, 2),
        ];
    }

    public function title(): string
    {
        return 'Reporte Renta D138 ' . $this->planilla->codigo;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
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
        $sheet->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode($this->moneyFormat);
        $sheet->getStyle("H2:I{$lastRow}")->getNumberFormat()->setFormatCode($this->moneyFormat);

        return [];
    }
}
