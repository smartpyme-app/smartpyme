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
            'Subtotal',
            'IVA',
            'Total',
            'Observaciones',
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
                    ->when($request->recurrente !== null, function($q) use ($request){
                        $q->where('recurrente', !!$request->recurrente);
                    })
                    ->when($request->id_usuario, function($query) use ($request){
                        return $query->where('id_usuario', $request->id_usuario);
                    })
                    ->when($request->id_sucursal, function($query) use ($request){
                        return $query->where('id_sucursal', $request->id_sucursal);
                    })
                    ->when($request->buscador, function($query) use ($request){
                        return $query->where('concepto', 'like' ,'%' . $request->buscador . '%')
                            ->orwhere('referencia', 'like', '%'.$request->buscador.'%');
                    })
                    ->when($request->inicio, function($query) use ($request){
                        return $query->where('fecha', '>=', $request->inicio);
                    })
                    ->when($request->fin, function($query) use ($request){
                        return $query->where('fecha', '<=', $request->fin);
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
              $row->nombre_proveedor,
              $row->proveedor()->pluck('nit')->first(),
              $row->proveedor()->pluck('ncr')->first(),
              number_format($row->sub_total,2),
              number_format($row->iva,2),
              number_format($row->total,2),
              $row->nota,
         ];
        return $fields;
    }
}
