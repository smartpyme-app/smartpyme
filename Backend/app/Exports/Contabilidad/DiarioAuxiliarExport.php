<?php

namespace App\Exports\Contabilidad;

use App\Models\Cuenta;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class DiarioAuxiliarExport implements FromView, WithStyles
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
        return view('reportes.contabilidad.excel.libro_diario_excel', [
            'empresa' => $this->data['empresa'],
            'month_name' => $this->data['month_name'],
            'year' => $this->data['year'],
            'reporteLibroDiario' => $this->data['reporteLibroDiario'],
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(60);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(30);

        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A2:H2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:H3')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A4:H4')->getAlignment()->setHorizontal('center');

        $sheet->getRowDimension('1')->setRowHeight(35);
        $sheet->getRowDimension('2')->setRowHeight(35);
        $sheet->getRowDimension('3')->setRowHeight(35);
        $sheet->getRowDimension('4')->setRowHeight(35);
        $sheet->getRowDimension('5')->setRowHeight(35);
        $sheet->getRowDimension('6')->setRowHeight(35);
        $sheet->getRowDimension('7')->setRowHeight(35);
        $sheet->getRowDimension('8')->setRowHeight(35);

        return [
            'A1:H1' => ['font' => ['bold' => true, 'size' => 16]],
            'A2:H2' => ['font' => ['bold' => true, 'size' => 16]],
            'A3:H3' => ['font' => ['bold' => true, 'size' => 16]],
            'A4:H4' => ['font' => ['bold' => true, 'size' => 16]],
            'A5:H5' => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }
}
