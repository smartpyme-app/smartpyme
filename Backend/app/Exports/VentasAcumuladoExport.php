<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PgSql\Lob;

class VentasAcumuladoExport implements WithMultipleSheets 
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {

        Log::info('VentasAcumuladoExport');
        Log::info($this->request);
        return [
            new VentasProductoSheet($this->request), 
            new VentasCategoriaSheet($this->request)
        ];
    }
}