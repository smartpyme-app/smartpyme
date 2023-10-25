<?php

namespace App\Exports;

use App\Models\Compra;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class ComprasExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    private $dateFrom;
    private $dateTo;
    private $state;
    private $id_proveedor;

    public function filter(Request $request)
    {
        $this->dateFrom = $request->fecha_de;
        $this->dateTo = $request->fecha_hasta;
        $this->state = $request->estado;
        $this->id_proveedor = $request->id_proveedor;
    }

    public function headings():array{
        return[
            'Fecha',
            'Proveedor',
            'DUI',
            'NIT',
            'Documento',
            'Referencia',
            'Estado', 
            'Vencimiento', 
            'Costo',
            'IVA', 
            'Percepción', 
            'Descuento', 
            'Total',
        ];

    }

    public function collection()
    {
        $id_proveedor = $this->id_proveedor;
        $dateFrom = $this->dateFrom;
        $state = $this->state;
        $dateTo = $this->dateTo;
        
        $compras = Compra::where('id_empresa', Auth::user()->id_empresa)
                      ->when($id_proveedor, function($q) use ($id_proveedor){
                          return $q->where('id_proveedor', $id_proveedor);
                      })
                      ->when($state, function($q) use ($state){
                        return $q->where('estado', $state);
                      })
                      ->when($dateFrom, function($q) use ($dateFrom, $dateTo){
                        return $q->whereBetween('fecha', [$dateFrom, $dateTo]);
                      })->orderBy('fecha', 'desc')->get();

        return $compras; 
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->proveedor()->pluck('nombre')->first(),
              $row->proveedor()->pluck('dui')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->documento,
              $row->num_referencia,
              $row->estado,
              $row->vencimiento,
              $row->sub_total,
              $row->iva,
              $row->percepcion,
              $row->descuento,
              $row->total_compra,
         ];
        return $fields;
    }
}
