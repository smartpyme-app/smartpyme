<?php

namespace App\Exports\Contabilidad;

use App\Models\Cuenta;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class BalanceComprobacionExport implements FromView, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * View for Excel
     *
     * @return View
     */
    public function view(): View
    {
        return view('reportes.contabilidad.excel.balance_comprobacion_excel', [
            'empresa' => $this->data['empresa'],
            'month_name' => $this->data['month_name'],
            'year' => $this->data['year'],
            'balanceComprobacion' => $this->data['balanceComprobacion'],
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);

        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');

        // Ajustar altura de filas para mayor visibilidad
        $sheet->getRowDimension('1')->setRowHeight(30);
        $sheet->getRowDimension('2')->setRowHeight(30);
        $sheet->getRowDimension('3')->setRowHeight(30);
        $sheet->getRowDimension('5')->setRowHeight(30);
        $sheet->getRowDimension('6')->setRowHeight(30);

        return [
            'A1:F1' => ['font' => ['bold' => true, 'size' => 14]],
            'A2:F2' => ['font' => ['bold' => true, 'size' => 14]],
            'A3:F3' => ['font' => ['bold' => true, 'size' => 14]],
            'A5:F5' => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }
}
