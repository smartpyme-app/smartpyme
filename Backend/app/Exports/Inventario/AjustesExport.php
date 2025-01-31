<?php

namespace App\Exports\Inventario;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Ajuste;

class AjustesExport implements FromCollection, WithHeadings, WithMapping
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
            'Sucursal',
            'Stock Inicial',
            'Ajuste',
            'Stock Final',
            'Usuario',
            'Fecha',
            'Estado',
            'Motivo',
        ];
    }

    public function map($row): array{
           $fields = [
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->bodega()->pluck('nombre')->first(),
              $row->stock_actual,
              $row->ajuste,
              $row->stock_real,
              $row->usuario()->pluck('name')->first(),
              \Carbon\Carbon::parse($row->created_at)->format('d/m/Y'),
              $row->estado,
              $row->concepto,
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;
        return Ajuste::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega, function($query) use ($request){
                                return $query->whereHas('bodega', function($q) use ($request){
                                    $q->where('id_bodega', $request->id_bodega);
                                });
                            })
                            ->when($request->id_usuario, function($query) use ($request){
                                return $query->whereHas('usuario', function($q) use ($request){
                                    $q->where('id_usuario', $request->id_usuario);
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
