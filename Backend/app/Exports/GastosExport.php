<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Compras\Gastos\Gasto;

class GastosExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
        return[
            'Fecha',
            'Concepto',
            'Categoria',
            'Estado',
            'Forma pago',
            'Referencia',
            'Banco',
            'Vencimiento',
            'Proveedor',
            'NIT',
            'Registro',
            'Monto sin IVA',
            'IVA',
            'Monto total',
            'Nota',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Gasto::when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
                        })
                    ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                    ->when($request->buscador, function($query) use ($request){
                        return $query->where('concepto', 'like' ,'%' . $request->buscador . '%');
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->concepto,
              $row->tipo,
              $row->estado == 'Confirmado' ? 'Pagado' : $row->estado,
              $row->forma_pago,
              $row->factura,
              $row->detalle_banco,
              $row->vencimiento,
              $row->proveedor()->pluck('nombre')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->proveedor()->pluck('ncr')->first(),
              round($row->total - $row->iva ,2),
              $row->iva,
              $row->total,
              $row->nota,
         ];
        return $fields;
    }
}
