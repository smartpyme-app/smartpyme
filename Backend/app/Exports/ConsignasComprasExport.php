<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Compras\Detalle;

class ConsignasComprasExport implements FromCollection, WithHeadings, WithMapping
{
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings(): array {
        return [
            'Producto',
            'Categoría',
            'Costo',
            'Código',
            'Stock en Consigna',
        ];
    }

    public function map($row): array {
        return [
            $row['nombre'],
            $row['nombre_categoria'],
            $row['costo'],
            $row['codigo'],
            $row['stock'],
        ];
    }

    public function collection()
    {
        $detallesDeCompra = Detalle::whereHas('compra', function($query){
                                $query->where('estado', 'Consigna');
                            })
                            ->with('producto.categoria', 'compra')
                            ->get()
                            ->groupBy('id_producto');

        $detalles = collect();

        foreach ($detallesDeCompra as $detallesGroup) {
            $producto = $detallesGroup[0]->producto()->first();

            if ($producto) {
                $detalles->push([
                    'nombre'             => $producto->nombre,
                    'nombre_categoria'   => $producto->nombre_categoria,
                    'costo'              => $detallesGroup[0]->costo,
                    'codigo'             => $producto->codigo,
                    'stock'              => $detallesGroup->sum('cantidad'),
                ]); 
            }
        }

        return $detalles;
    }
}
