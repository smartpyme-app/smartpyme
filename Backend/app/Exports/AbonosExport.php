<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;

class AbonosExport implements FromCollection, WithHeadings, WithMapping
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
            'Cliente',
            'DUI',
            'Documento',
            'Correlativo',
            'Concepto',
            'Estado',
            'Forma pago',
            'Banco',
            'Total',
            'Nota',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Abono::with('venta')->when($request->buscador, function($query) use ($request){
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
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
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
              $row->venta()->first() ? $row->venta()->first()->nombre_cliente : '',
              $row->venta()->first() ? $row->venta()->first()->cliente()->pluck('dui')->first() : '',
              $row->venta()->first() ? $row->venta()->first()->documento()->pluck('nombre')->first() : '',
              $row->venta()->first() ? $row->venta()->pluck('correlativo')->first() : '',
              $row->concepto,
              $row->estado == 'Confirmado' ? 'Pagado' : $row->estado,
              $row->forma_pago,
              $row->detalle_banco,
              number_format($row->total,2),
              $row->nota,
         ];
        return $fields;
    }
}
