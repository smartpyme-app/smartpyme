<?php

namespace App\Exports\Contabilidad;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;

class EstadoResultadosExport implements FromView, WithStyles
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
        return view('reportes.contabilidad.excel.estado_resultados_excel', [
            'estado_resultados' => $this->data['estado_resultados'],
            'empresa' => $this->data['empresa'],
            'month_name' => $this->data['month_name'],
            'month' => $this->data['month'],
            'year' => $this->data['year']
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);

        $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A2:D2')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:D3')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A4:D4')->getAlignment()->setHorizontal('center');

        // Ajustar altura de filas para mayor visibilidad
        $sheet->getRowDimension('1')->setRowHeight(30);
        $sheet->getRowDimension('2')->setRowHeight(30);
        $sheet->getRowDimension('3')->setRowHeight(30);
        $sheet->getRowDimension('4')->setRowHeight(30);

        return [
            'A1:D1' => ['font' => ['bold' => true, 'size' => 14]],
            'A2:D2' => ['font' => ['bold' => true, 'size' => 14]],
            'A3:D3' => ['font' => ['bold' => true, 'size' => 14]],
            'A4:D4' => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }
}
