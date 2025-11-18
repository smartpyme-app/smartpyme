<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Compras\Gastos\Abono;

class AbonosGastosExport implements FromCollection, WithHeadings, WithMapping
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
            'NIT',
            'Documento',
            'Referencia',
            'Concepto',
            'Estado',
            'Forma pago',
            'Banco',
            'Referencia Abono',
            'Total',
            'Nota',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Abono::with('gasto')->when($request->buscador, function($query) use ($request){
                        return $query->orwhere('id_gasto', 'like', '%'.$request->buscador.'%')
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
                            return $query->whereHas('gasto', function($q) use ($request){
                                return $q->where('id_proveedor', $request->id_proveedor);
                            });
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
                        ->orderBy($request->orden ?? 'id', $request->direccion ?? 'desc')
                        ->orderBy('id', 'desc')
                    ->get();
        
    }

    public function map($row): array{
           $gasto = $row->gasto()->first();
           $proveedor = $gasto ? $gasto->proveedor()->first() : null;
           
           $fields = [
              $row->fecha,
              $gasto ? $gasto->nombre_proveedor : '',
              $proveedor ? ($proveedor->nit ?? '') : '',
              $gasto ? $gasto->tipo_documento : '',
              $gasto ? $gasto->referencia : '',
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

