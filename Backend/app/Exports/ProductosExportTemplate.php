<?php

namespace App\Exports;

use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductosExportTemplate implements FromCollection,  WithHeadings
{

    public function headings():array{
       return[
            'Nombre',
            'Precio',
            'Costo',
            'Stock',
            'Categoria',
            'Codigo',
            'Descripcion',
            'Marca',
            'Codigo_de_barra',
            'Proveedor',
        ];
    }

    public function collection()
    {
        return Producto::where('id', 0)->get();
    }
    
}
