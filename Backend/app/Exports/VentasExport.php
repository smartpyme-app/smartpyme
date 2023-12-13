<?php

namespace App\Exports;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasExport implements FromCollection, WithHeadings, WithMapping
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
            'Dirección',
            'Documento', 
            'Correlativo', 
            'Forma de pago',
            'Banco',
            'Estado',
            'Canal',
            'Costo',
            'Sub Total',
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
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::when($request->buscador, function($query) use ($request){
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
                            ->where('estado', '!=', 'Pre-venta')
                            ->get();

        return $ventas;
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->cliente()->pluck('nombre')->first(),
              $row->cliente()->pluck('dui')->first(),
              $row->cliente()->pluck('nit')->first(),
              $row->cliente()->pluck('direccion')->first(),
              $row->nombre_documento,
              $row->correlativo,
              $row->forma_pago,
              $row->detalle_banco,
              $row->estado,
              $row->nombre_canal,
              $row->total_costo,
              $row->sub_total,
              $row->descuento,
              $row->iva,
              $row->total - $row->total_costo - $row->iva,
              $row->total,
              $row->sucursal()->first()->empresa()->pluck('nombre')->first(),
              $row->observaciones,
              $row->nombre_usuario,

         ];
        return $fields;
    }
}
