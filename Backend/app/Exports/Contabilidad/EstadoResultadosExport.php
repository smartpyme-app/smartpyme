<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class EstadoResultadosExport implements FromView
{
    protected $estadoResultados;
    protected $empresa;
    protected $monthName;
    protected $month;
    protected $year;

    public function __construct($estadoResultados, $empresa, $monthName, $month, $year)
    {
        $this->estadoResultados = $estadoResultados;
        $this->empresa = $empresa;
        $this->monthName = $monthName;
        $this->month = $month;
        $this->year = $year;
    }

    public function view(): View
    {
        return view('reportes.contabilidad.excel.estado_resultados_excel', [
            'estado_resultados' => $this->estadoResultados,
            'empresa' => $this->empresa,
            'month_name' => $this->monthName,
            'month' => $this->month,
            'year' => $this->year
        ]);
    }
}
