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

class PlanillaDetallesExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
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
            'Código Empleado',
            'Nombres',
            'Apellidos',
            'DUI',
            'NIT',
            'ISSS',
            'AFP',
            'Departamento',
            'Cargo',
            'Días Laborados',
            'Salario Base',
            'Horas Extra',
            'Monto Horas Extra',
            'Comisiones',
            'Bonificaciones',
            'Otros Ingresos',
            'Total Ingresos',
            'Salario Devengado',
            'Préstamos',
            'Anticipos',
            'Desc. Judiciales',
            'Otros Descuentos',
            'ISSS Empleado',
            'AFP Empleado',
            'Renta',
            'Total Descuentos',
            'Sueldo Neto',
            'Viáticos',
            'Total a Pagar',
            'ISSS Patronal',
            'AFP Patronal',
            'Total Patronal',
            'Estado',
            'Observaciones'
        ];
    }

    public function map($detalle): array
    {
        // Calcular totales
        $totalIngresos = ($detalle->salario_base ?? 0) + 
                        ($detalle->monto_horas_extra ?? 0) + 
                        ($detalle->comisiones ?? 0) + 
                        ($detalle->bonificaciones ?? 0) + 
                        ($detalle->otros_ingresos ?? 0);

        $totalDescuentos = ($detalle->prestamos ?? 0) + 
                          ($detalle->anticipos ?? 0) + 
                          ($detalle->descuentos_judiciales ?? 0) + 
                          ($detalle->otros_descuentos ?? 0) + 
                          ($detalle->isss ?? 0) + 
                          ($detalle->afp ?? 0) + 
                          ($detalle->renta ?? 0);

        $totalPatronal = ($detalle->isss_patronal ?? 0) + ($detalle->afp_patronal ?? 0);

        // Determinar estado
        switch ($detalle->estado) {
            case 1:
                $estadoTexto = 'Activo';
                break;
            case 2:
                $estadoTexto = 'Incluido';
                break;
            case 3:
                $estadoTexto = 'Retirado';
                break;
            case 4:
                $estadoTexto = 'Pagado';
                break;
            default:
                $estadoTexto = 'Inactivo';
                break;
        }

        return [
            $detalle->empleado_codigo ?? '',
            $detalle->nombres ?? '',
            $detalle->apellidos ?? '',
            $detalle->dui ?? '',
            $detalle->nit ?? '',
            $detalle->empleado_isss ?? '',
            $detalle->empleado_afp ?? '',
            $detalle->departamento_nombre ?? '',
            $detalle->cargo_nombre ?? '',
            $detalle->dias_laborados ?? 30,
            round($detalle->salario_base ?? 0, 2),
            $detalle->horas_extra ?? 0,
            round($detalle->monto_horas_extra ?? 0, 2),
            round($detalle->comisiones ?? 0, 2),
            round($detalle->bonificaciones ?? 0, 2),
            round($detalle->otros_ingresos ?? 0, 2),
            round($totalIngresos, 2),
            round($detalle->salario_devengado ?? 0, 2),
            round($detalle->prestamos ?? 0, 2),
            round($detalle->anticipos ?? 0, 2),
            round($detalle->descuentos_judiciales ?? 0, 2),
            round($detalle->otros_descuentos ?? 0, 2),
            round($detalle->isss ?? 0, 2),
            round($detalle->afp ?? 0, 2),
            round($detalle->renta ?? 0, 2),
            round($totalDescuentos, 2),
            round($detalle->sueldo_neto ?? 0, 2),
            round($detalle->viaticos ?? 0, 2),
            round(($detalle->sueldo_neto ?? 0) + ($detalle->viaticos ?? 0), 2),
            round($detalle->isss_patronal ?? 0, 2),
            round($detalle->afp_patronal ?? 0, 2),
            round($totalPatronal, 2),
            $estadoTexto,
            $detalle->observaciones ?? ''
        ];
    }

    public function title(): string
    {
        return 'Detalles Planilla ' . $this->planilla->codigo;
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para encabezados
        $sheet->getStyle('A1:AF1')->applyFromArray([
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
        $sheet->getStyle('A1:AF' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);

        // Formato numérico para columnas de dinero
        $moneyColumns = ['K', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD'];
        foreach ($moneyColumns as $col) {
            $sheet->getStyle($col . '2:' . $col . $lastRow)->getNumberFormat()->setFormatCode('$#,##0.00');
        }

        // Ajustar altura de la fila de encabezados
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }
}