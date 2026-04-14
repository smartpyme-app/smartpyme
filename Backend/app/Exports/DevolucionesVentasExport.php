<?php

namespace App\Exports;

use App\Models\Ventas\Devoluciones\Devolucion;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class DevolucionesVentasExport implements FromCollection, WithHeadings, WithMapping
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
            'Documento', 
            'Correlativo', 
            'Estado',
            'Total',
            'Empresa',
            'Observaciones', 
            'Usuario'
        ];
    }

    public function collection()
    {
        $request = $this->request ?? request();
        if (!$request) {
            $request = new Request();
        }

        $orden = $request->input('orden', 'fecha');
        $direccion = $request->input('direccion', 'desc');
        
        $ventas = Devolucion::when($request->buscador, function($query) use ($request){
                            return $query->where('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->estado !== null, function($q) use ($request){
                            $q->where('enable', !!$request->estado);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                    ->orderBy($orden, $direccion)
                    ->orderBy('id', 'desc')
                    ->get();

        return $ventas;
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->cliente()->pluck('nombre')->first(),
              $row->cliente()->pluck('dui')->first(),
              $row->cliente()->pluck('nit')->first(),
              $row->venta()->first()->nombre_documento,
              $row->venta()->first()->correlativo,
              ($row->enable ? 'Activa' : 'Anulada'),
              round($row->total, 2),
              $row->empresa()->pluck('nombre')->first(),
              $row->observaciones,
              $row->nombre_usuario,

         ];
        return $fields;
    }
}
