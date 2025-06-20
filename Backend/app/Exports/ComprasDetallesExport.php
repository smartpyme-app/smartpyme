<?php

namespace App\Exports;

use App\Models\Compras\Detalle;
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
    public $request;

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
            'Producto',
            'Categoria',
            'Documento',
            'Referencia',
            'Proyecto',
            'Num Identificacion',
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
        $request = $this->request;
        
        $detalles = Detalle::whereHas('compra', function($query) use ($request) {
                            $query->when($request->buscador, function($query) use ($request){
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
                            ->orderBy('id', 'desc');
                        })->get();

        return $detalles;
        
    }

    public function map($row): array{
           $fields = [
              $row->compra()->pluck('fecha')->first(),
              $row->compra()->first() ? $row->compra()->first()->nombre_proveedor : 'Comsumidor Final',
              $row->compra()->first()->proveedor()->pluck('dui')->first(),
              $row->compra()->first()->proveedor()->pluck('nit')->first(),
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->compra()->pluck('tipo_documento')->first(),
              $row->compra()->pluck('referencia')->first(),
              $row->compra()->first()->nombre_proyecto,
              $row->compra()->pluck('num_identificacion')->first(),
              $row->compra()->pluck('estado')->first(),
              $row->compra()->pluck('fecha_pago')->first(),
              $row->cantidad,
              $row->costo,
              $row->total,
              $row->total * 0.13,
              $row->descuento,
              $row->percepcion,
              $row->total + ($row->total * 0.13) + $row->percepcion,

         ];
        return $fields;
    }
}
