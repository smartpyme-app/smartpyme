<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VentasPorUtilidadesExport implements WithMultipleSheets 
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {

        // Log::info('VentasPorUtilidadesExport');
        // Log::info($this->request);
        return [
            new VentasPorUtilidadesSheet($this->request), 
        ];
    }
}