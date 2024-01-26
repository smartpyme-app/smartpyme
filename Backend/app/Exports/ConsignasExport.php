<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Detalle;

class ConsignasExport implements FromCollection, WithHeadings, WithMapping
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
            'Precio',
            'Codigo',
            'Stock',
        ];
    }

    public function map($row): array{
        $fields = [
              $row['nombre'],
              $row['nombre_categoria'],
              $row['precio'],
              $row['codigo'],
              $row['stock'],
         ];
        return $fields;
    }

    public function collection()
    {
        $request = $this->request;

        $detallesDeVenta = Detalle::whereHas('venta', function($query){
                                $query->where('estado', 'Consigna');
                            })
                            ->with('producto.categoria', 'venta')
                            ->get()
                            ->groupBy('id_producto');


        $detalles = collect();

        foreach ($detallesDeVenta as $detallesGroup) {
            $ventas = collect();
            
            foreach ($detallesGroup as $detalle) {
                $ventas->push([
                    'fecha'         => $detalle->venta->fecha,
                    'cliente'       => $detalle->venta->nombre_cliente,
                    'cantidad'      => $detalle->cantidad,
                    'id'            => $detalle->venta->id,
                    'nombre_documento'            => $detalle->venta->nombre_documento,
                    'correlativo'            => $detalle->venta->correlativo,
                    'fecha_pago'    => $detalle->venta->fecha_pago,
                ]);
            }
            $producto = $detallesGroup[0]->producto()->first();

            $detalles->push([
                'nombre'             => $producto->nombre,
                'img'                => $producto->img,
                'nombre_categoria'   => $producto->nombre_categoria,
                'precio'             => $detallesGroup[0]->precio,
                'codigo'             => $producto->codigo,
                'stock'              => $detallesGroup->sum('cantidad'),
                'ventas'             => $ventas,
            ]); 
        }


        return $detalles;
    }
}
