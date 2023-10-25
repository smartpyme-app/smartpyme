<?php

namespace App\Exports;

use App\Models\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $fecha_de;
    public $fecha_hasta;
    public $estado;
    public $cliente;
    public $sucursal;

    public function filter(Request $request)
    {
        $this->fecha_de = $request->fecha_de;
        $this->fecha_hasta = $request->fecha_hasta;
        $this->estado = $request->estado;
        $this->cliente = $request->id_cliente;
        $this->sucursal = $request->id_sucursal;
    }

    public function headings():array{
        return[
            'Fecha',
            'Cliente',
            'DUI',
            'NIT',
            'Dirección',
            'Documento', 
            'Correlativo', 
            'Forma de pago',
            'Estado',
            'Canal',
            'Costo',
            'Sub Total',
            'Descuento',
            'IVA',
            'Utilidad',
            'Total',
            'Empresa',
            'Observaciones', 
            'Usuario'
        ];
    }

    public function collection()
    {
        $estado = $this->estado;
        $cliente = $this->cliente;
        $fecha_de = $this->fecha_de;
        $fecha_hasta = $this->fecha_hasta;
        $sucursal = $this->sucursal;
        
        $ventas = Venta::where('id_empresa', Auth::user()->id_empresa)
                      ->when($sucursal, function($q) use ($sucursal){
                         return $q->where('id_sucursal', $sucursal);
                      })
                      ->where('estado', '!=', 'Pre-venta')
                      ->when($estado, function($q) use ($estado){
                         return $q->where('estado', $estado);
                      })
                      ->when($cliente, function($q) use ($cliente){
                          return $q->where('id_cliente', $cliente);
                      })
                      ->when($estado, function($q) use ($estado){
                        return $q->where('estado', $estado);
                      })
                      ->when($fecha_de, function($q) use ($fecha_de, $fecha_hasta){
                        return $q->whereBetween('fecha', [$fecha_de, $fecha_hasta]);
                      })->orderBy('fecha', 'desc')->get();

        return $ventas;
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->cliente()->pluck('nombre')->first(),
              $row->cliente()->pluck('dui')->first(),
              $row->cliente()->pluck('nit')->first(),
              $row->cliente()->pluck('direccion')->first(),
              $row->documento,
              $row->correlativo,
              $row->forma_pago,
              $row->estado,
              $row->canal,
              $row->total_costo,
              $row->sub_total,
              $row->descuento,
              $row->iva,
              $row->total_venta - $row->total_costo - $row->iva,
              $row->total_venta,
              $row->sucursal()->first()->empresa()->pluck('nombre')->first(),
              $row->observaciones,
              $row->usuario,

         ];
        return $fields;
    }
}
