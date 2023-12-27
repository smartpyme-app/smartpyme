<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Inventario\Kardex;

class KardexExport implements FromCollection, WithHeadings, WithMapping
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
            'Producto',
            'Inventario',
            'Detalle',
            'Referencia',
            
            'Entrada Cantidad',
            'Entrada Costo U',
            'Entrada Total',
            
            'Salida Cantidad',
            'Salida Precio U',
            'Salida Total',
            
            'Saldo Cantidad',
            'Saldo Total',
            
            'Usuario',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Kardex::where('id_producto', $request->id_producto)
                        ->when($request->id_inventario, function($q) use ($request){
                            $q->where('id_inventario', $request->id_inventario);
                        })
                        ->when($request->inicio, function($q) use ($request){
                            $q->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($q) use ($request){
                            $q->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->detalle, function($q) use ($request){
                            return $q->where('detalle', 'like' ,'%' . $request->detalle . '%');
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->producto()->pluck('nombre')->first(),
              $row->inventario()->first() ? $row->inventario()->first()->sucursal()->pluck('nombre')->first() : '',
              $row->detalle,
              $row->referencia,
              $row->entrada_cantidad,
              $row->costo_unitario,
              $row->entrada_valor,
              $row->salida_cantidad,
              $row->precio_unitario,
              $row->salida_valor,
              $row->total_cantidad,
              $row->total_valor,
              $row->usuario()->pluck('name')->first(),
         ];
        return $fields;
    }
}
