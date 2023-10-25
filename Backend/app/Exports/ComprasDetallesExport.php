<?php

namespace App\Exports;

use App\Models\DetalleCompra;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class ComprasDetallesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $fecha_de;
    public $fecha_hasta;
    public $estado;
    public $proveedor;
    public $sucursal;

    public function filter(Request $request)
    {
        $this->fecha_de = $request->fecha_de;
        $this->fecha_hasta = $request->fecha_hasta;
        $this->estado = $request->estado;
        $this->proveedor = $request->id_proveedor;
        $this->sucursal = $request->id_sucursal;
    }

    public function headings():array{
        return[
            'Fecha',
            'Proveedor',
            'DUI',
            'NIT',
            'Producto',
            'Categoria',
            'Documento',
            'Estado',
            'Vencimiento',
            'Cantidad',
            'Costo',
            'Sub Total',
            'IVA',
            'Descuento',
            'Percepción',
            'Total',
        ];
    }

    public function collection()
    {
        $estado = $this->estado;
        $proveedor = $this->proveedor;
        $fecha_de = $this->fecha_de;
        $fecha_hasta = $this->fecha_hasta;
        $sucursal = $this->sucursal;
        
        $detalles = DetalleCompra::whereHas('compra', function($query) use ($sucursal, $estado, $proveedor, $fecha_hasta, $fecha_de) {
                              $query->where('id_empresa', Auth::user()->id_empresa)
                              ->when($sucursal, function($q) use ($sucursal){
                                 return $q->where('id_sucursal', $sucursal);
                              })
                              ->when($estado, function($q) use ($estado){
                                 return $q->where('estado', $estado);
                              })
                              ->when($proveedor, function($q) use ($proveedor){
                                  return $q->where('id_proveedor', $proveedor);
                              })
                              ->when($fecha_de, function($q) use ($fecha_de, $fecha_hasta){
                                return $q->whereBetween('fecha', [$fecha_de, $fecha_hasta]);
                              });
                        })->orderBy('created_at', 'desc')->get();

        return $detalles;
        
    }

    public function map($row): array{
           $fields = [
              $row->compra()->pluck('fecha')->first(),
              $row->compra()->first()->proveedor()->pluck('nombre')->first(),
              $row->compra()->first()->proveedor()->pluck('dui')->first(),
              $row->compra()->first()->proveedor()->pluck('nit')->first(),
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->compra()->pluck('documento')->first() . ': ' . $row->compra()->pluck('num_referencia')->first(),
              $row->compra()->pluck('estado')->first(),
              $row->compra()->pluck('vencimiento')->first(),
              $row->cantidad,
              $row->costo,
              $row->sub_total,
              $row->sub_total * 0.13,
              $row->descuento,
              $row->percepcion,
              $row->sub_total + ($row->sub_total * 0.13) + $row->percepcion,

         ];
        return $fields;
    }
}
