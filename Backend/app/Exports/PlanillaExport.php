<?php
// app/Exports/PlanillaExport.php
namespace App\Exports;

use App\Helpers\CurrencyHelper;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanillaExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting
{
    protected $planilla;
    protected $moneyFormat;
    
    public function __construct($planilla)
    {
        $this->planilla = $planilla->loadMissing('empresa.currency');
        $this->moneyFormat = CurrencyHelper::excelFormat($this->planilla->empresa);
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
                'Sueldo Neto' => $detalle->sueldo_neto,
                'Viáticos' => $detalle->viaticos ?? 0,
                'Total a Pagar' => ($detalle->sueldo_neto ?? 0) + ($detalle->viaticos ?? 0)
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
            'Sueldo Neto',
            'Viáticos',
            'Total a Pagar'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A1:T1' => [
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
        $moneyColumns = ['C', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'];
        $formats = [];

        foreach ($moneyColumns as $column) {
            $formats[$column] = $this->moneyFormat;
        }

        return $formats;
    }
}