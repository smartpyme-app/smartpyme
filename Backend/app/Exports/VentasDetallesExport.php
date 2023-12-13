<?php

namespace App\Exports;

use App\Models\Ventas\Detalle;
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
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
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
            'Correlativo',
            'Forma de pago',
            'Banco',
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
        $request = $this->request;
        
        $detalles = Detalle::whereHas('venta', function($query) use ($request) {
                              $query->when($request->buscador, function($query) use ($request){
                                return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                            ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                            ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                            ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
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
                                ->when($request->id_canal, function($query) use ($request){
                                    return $query->where('id_canal', $request->id_canal);
                                })
                                ->when($request->id_documento, function($query) use ($request){
                                    return $query->where('id_documento', $request->id_documento);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->metodo_pago, function($query) use ($request){
                                    return $query->where('metodo_pago', $request->metodo_pago);
                                })
                                ->when($request->tipo_documento, function($query) use ($request){
                                    return $query->where('tipo_documento', $request->tipo_documento);
                                })
                            ->orderBy($request->orden, $request->direccion)
                            ->orderBy('id', 'desc')
                            ->where('estado', '!=', 'Pre-venta');
                        })->get();

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
              $row->venta()->first()->documento()->pluck('nombre')->first(),
              $row->venta()->pluck('correlativo')->first(),
              $row->venta()->pluck('forma_pago')->first(),
              $row->venta()->pluck('detalle_banco')->first(),
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
              $row->venta()->first()->usuario()->pluck('name')->first(),
         ];
        return $fields;
    }
}
