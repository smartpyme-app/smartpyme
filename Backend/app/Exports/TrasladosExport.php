<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Traslado;

class TrasladosExport implements FromCollection, WithHeadings, WithMapping
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
            'Producto',
            'Categoria',
            'De',
            'Para',
            'Cantidad',
            'Usuario',
            'Fecha',
            'Estado',
            'Motivo',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->first()->categoria()->pluck('nombre')->first(),
              $row->origen()->pluck('nombre')->first(),
              $row->destino()->pluck('nombre')->first(),
              $row->cantidad,
              $row->usuario()->pluck('name')->first(),
              \Carbon\Carbon::parse($row->fecha)->format('d/m/Y'),
              $row->estado,
              $row->concepto,
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Traslado::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_sucursal_de, function($query) use ($request){
                                return $query->whereHas('origen', function($q) use ($request){
                                    $q->where('id_sucursal_de', $request->id_sucursal_de);
                                });
                            })
                            ->when($request->id_sucursal_para, function($query) use ($request){
                                return $query->whereHas('destino', function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal_para);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                });
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden, $request->direccion)
                    ->get();
    }
}
