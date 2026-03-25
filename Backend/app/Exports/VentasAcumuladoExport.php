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

        // Log::info('VentasAcumuladoExport');
        // Log::info($this->request);
        
        // Verificar si se solicita detalle por sucursal
        $detallePorSucursal = $this->request->detallePorSucursal ?? false;
        
        // Convertir string "true"/"false" a boolean si es necesario
        if (is_string($detallePorSucursal)) {
            $detallePorSucursal = filter_var($detallePorSucursal, FILTER_VALIDATE_BOOLEAN);
        }
        
        // Usar VentasProductoDetalleSheet si detallePorSucursal es true, sino VentasProductoSheet
        $productoSheet = $detallePorSucursal 
            ? new VentasProductoDetalleSheet($this->request)
            : new VentasProductoSheet($this->request);
        
        // Usar VentasCategoriaDetalleSheet si detallePorSucursal es true, sino VentasCategoriaSheet
        $categoriaSheet = $detallePorSucursal 
            ? new VentasCategoriaDetalleSheet($this->request)
            : new VentasCategoriaSheet($this->request);
        
        return [
            $productoSheet, 
            $categoriaSheet
        ];
    }
}