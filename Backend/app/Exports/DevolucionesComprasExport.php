<?php

namespace App\Exports;

use App\Models\Compras\Devoluciones\Devolucion;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class DevolucionesComprasExport implements FromCollection, WithHeadings, WithMapping
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
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $compras = Devolucion::when($request->buscador, function($query) use ($request){
                            return $query->where('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('enable', $request->estado);
                        })
                        ->when($request->forma_de_pago, function($query) use ($request){
                            return $query->where('forma_de_pago', $request->forma_de_pago);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->whereHas('proveedor', function($query) use ($request)
                            {
                                $query->where('id_proveedor', $request->id_proveedor);

                            });
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->get();

        return $compras;
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->proveedor()->pluck('nombre')->first(),
              $row->proveedor()->pluck('dui')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->compra()->first()->tipo_documento,
              $row->compra()->first()->referencia,
              $row->estado,
              round($row->total, 2),
              $row->empresa()->pluck('nombre')->first(),
              $row->observaciones,
              $row->nombre_usuario,

         ];
        return $fields;
    }
}
