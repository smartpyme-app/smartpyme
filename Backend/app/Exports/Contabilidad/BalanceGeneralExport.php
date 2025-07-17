<?php

namespace App\Exports\Contabilidad;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class BalanceGeneralExport implements FromView, WithStyles
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
        return view('reportes.contabilidad.excel.balance_general_excel', [
            'empresa' => $this->data['empresa'],
            'month_name' => $this->data['month_name'],
            'year' => $this->data['year'],
            'balance_general' => $this->data['balance_general'],
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(20);

        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal('center');

        // Ajustar altura de filas
        $sheet->getRowDimension('1')->setRowHeight(30);
        $sheet->getRowDimension('2')->setRowHeight(30);
        $sheet->getRowDimension('3')->setRowHeight(30);

        return [
            'A1:F1' => ['font' => ['bold' => true, 'size' => 16]],
            'A2:F2' => ['font' => ['bold' => true, 'size' => 14]],
            'A3:F3' => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
