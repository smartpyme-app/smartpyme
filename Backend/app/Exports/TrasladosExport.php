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
            'Codigo',
            'Costo',
            'Precio',
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
           $producto = $row->producto()->first();
           $fields = [
              $producto?->nombre,
              $producto?->codigo,
              $producto?->costo,
              $producto?->precio,
              $producto?->categoria()->pluck('nombre')->first(),
              $row->origen()->pluck('nombre')->first(),
              $row->destino()->pluck('nombre')->first(),
              $row->cantidad,
              $row->usuario()->pluck('name')->first(),
              $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') : '',
              $row->estado,
              $row->concepto,
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Traslado::when($request->inicio, function($query) use ($request){
                                return $query->where('created_at', '>=', $request->inicio . ' 00:00:00');
                            })
                            ->when($request->fin, function($query) use ($request){
                                return $query->where('created_at', '<=', $request->fin . ' 23:59:59');
                            })
                            ->when($request->id_bodega_de, function($query) use ($request){
                                return $query->where('id_bodega_de', $request->id_bodega_de);
                            })
                            ->when($request->id_bodega_para, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega_para);
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->where(function($q) use ($request){
                                    $q->whereHas('producto', function($p) use ($request){
                                        $p->where('nombre', 'like',  '%'. $request->search . '%');
                                    })->orWhere('concepto', 'like',  '%'. $request->search . '%');
                                });
                            })
                            ->when($request->concepto, function($query) use ($request){
                                return $query->where('concepto', 'like', '%' . $request->concepto . '%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden ?? 'created_at', $request->direccion ?? 'desc')
                    ->get();
    }
}
