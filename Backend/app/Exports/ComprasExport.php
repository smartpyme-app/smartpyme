<?php

namespace App\Exports;

use App\Models\Compras\Compra;
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
            'NIT',
            'Documento',
            'Referencia',
            'Proyecto',
            'Num identificación',
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
        $request = $this->request;
        
        $compras = Compra::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->recurrente !== null, function($q) use ($request){
                            $q->where('recurrente', !!$request->recurrente);
                        })
                        ->when($request->id_proyecto, function($q) use ($request){
                            $q->where('id_proyecto', $request->id_proyecto);
                        })
                        ->when($request->num_identificacion, function($q) use ($request){
                            $q->where('num_identificacion', $request->num_identificacion);
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
                        ->where('cotizacion', 0)
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->get();

        return $compras; 
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->nombre_proveedor,
              $row->proveedor()->pluck('dui')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->tipo_documento,
              $row->referencia,
              $row->proyecto()->pluck('nombre')->first(),
              $row->num_identificacion,
              $row->estado,
              $row->fecha_pago,
              $row->sub_total,
              $row->iva,
              $row->percepcion,
              $row->descuento,
              $row->total,
         ];
        return $fields;
    }
}
