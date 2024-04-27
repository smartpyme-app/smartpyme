<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Compras\Abono;

class AbonosComprasExport implements FromCollection, WithHeadings, WithMapping
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
            'Proveedor',
            'DUI',
            'Documento',
            'Correlativo',
            'Concepto',
            'Estado',
            'Forma pago',
            'Banco',
            'Referencia',
            'Total',
            'Nota',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Abono::with('compra')->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('id_venta', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('concepto', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('nombre_de', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->compra()->first() ? $row->compra()->first()->nombre_proveedor : '',
              $row->compra()->first() ? $row->compra()->first()->proveedor()->pluck('dui')->first() : '',
              $row->compra()->first() ? $row->compra()->pluck('tipo_documento')->first() : '',
              $row->compra()->first() ? $row->compra()->pluck('referencia')->first() : '',
              $row->concepto,
              $row->estado == 'Confirmado' ? 'Pagado' : $row->estado,
              $row->forma_pago,
              $row->detalle_banco,
              $row->referencia,
              number_format($row->total,2),
              $row->nota,
         ];
        return $fields;
    }
}
