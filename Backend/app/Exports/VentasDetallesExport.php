<?php

namespace App\Exports;

use App\Models\DetalleVenta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasDetallesExport implements FromCollection, WithHeadings, WithMapping
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
            'Producto',
            'Marca',
            'Categoria',
            'Documento',
            'Forma de pago',
            'Estado',
            'Canal',
            'Cantidad',
            'Costo',
            'Precio',
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
        
        $detalles = DetalleVenta::whereHas('venta', function($query) use ($sucursal, $estado, $cliente, $fecha_hasta, $fecha_de) {
                              $query->where('id_empresa', Auth::user()->id_empresa)
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
                              ->when($fecha_de, function($q) use ($fecha_de, $fecha_hasta){
                                return $q->whereBetween('fecha', [$fecha_de, $fecha_hasta]);
                              });
                        })->orderBy('created_at', 'desc')->get();

        return $detalles;
        
    }

    public function map($row): array{
           $fields = [
              $row->venta()->pluck('fecha')->first(),
              $row->venta()->first()->cliente()->pluck('nombre')->first(),
              $row->venta()->first()->cliente()->pluck('dui')->first(),
              $row->venta()->first()->cliente()->pluck('nit')->first(),
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->pluck('marca')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->venta()->first()->documento()->pluck('nombre')->first() . ': ' . $row->venta()->pluck('correlativo')->first(),
              $row->venta()->pluck('forma_pago')->first(),
              $row->venta()->pluck('estado')->first(),
              $row->venta()->first()->canal()->pluck('nombre')->first(),
              $row->cantidad,
              $row->costo,
              $row->precio,
              $row->descuento,
              $row->venta()->first()->iva ? $row->total * 0.13 : 0,
              $row->total - $row->costo * $row->cantidad - ($row->venta()->first()->iva ? $row->total * 0.13 : 0),
              $row->total + ($row->venta()->first()->iva ? $row->total * 0.13 : 0),
              $row->venta()->first()->sucursal()->first()->empresa()->pluck('nombre')->first(),
              $row->venta()->pluck('observaciones')->first(),
              $row->venta()->first()->user()->pluck('name')->first(),
         ];
        return $fields;
    }
}
