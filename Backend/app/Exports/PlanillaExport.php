<?php
// app/Exports/PlanillaExport.php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanillaExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting
{
    protected $planilla;
    
    public function __construct($planilla)
    {
        $this->planilla = $planilla;
    }

    public function collection()
    {
        $data = [];
        foreach ($this->planilla->detalles as $detalle) {
            $data[] = [
                'Código' => $detalle->empleado->codigo,
                'Empleado' => $detalle->empleado->nombres . ' ' . $detalle->empleado->apellidos,
                'Salario Base' => $detalle->salario_base,
                'Días Laborados' => $detalle->dias_laborados,
                'Horas Extra' => $detalle->horas_extra,
                'Monto Horas Extra' => $detalle->monto_horas_extra,
                'Comisiones' => $detalle->comisiones,
                'Bonificaciones' => $detalle->bonificaciones,
                'Otros Ingresos' => $detalle->otros_ingresos,
                'Total Ingresos' => $detalle->total_ingresos,
                'ISSS' => $detalle->isss_empleado,
                'AFP' => $detalle->afp_empleado,
                'Renta' => $detalle->renta,
                'Préstamos' => $detalle->prestamos,
                'Anticipos' => $detalle->anticipos,
                'Otros Descuentos' => $detalle->otros_descuentos,
                'Total Descuentos' => $detalle->total_descuentos,
                'Sueldo Neto' => $detalle->sueldo_neto
            ];
        }
        
        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Código',
            'Empleado',
            'Salario Base',
            'Días Laborados',
            'Horas Extra',
            'Monto Horas Extra',
            'Comisiones',
            'Bonificaciones',
            'Otros Ingresos',
            'Total Ingresos',
            'ISSS',
            'AFP',
            'Renta',
            'Préstamos',
            'Anticipos',
            'Otros Descuentos',
            'Total Descuentos',
            'Sueldo Neto'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:R1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F81BD']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']]
            ]
        ];
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'F' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'G' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'H' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'I' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'J' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'K' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'L' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'M' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'N' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'O' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'P' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'Q' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'R' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE
        ];
    }
}