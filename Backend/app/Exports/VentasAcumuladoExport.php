<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Http\Request;

class VentasAcumuladoExport implements WithMultipleSheets 
{
    protected $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function sheets(): array
    {
        return [
            new VentasProductoSheet($this->request), 
            new VentasCategoriaSheet($this->request)
        ];
    }
}