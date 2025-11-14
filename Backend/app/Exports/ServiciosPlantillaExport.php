<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Inventario\Producto;

class ServiciosPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombre',
            'categoria',
            'costo',
            'precio',
            'codigo',
            'descripcion',
        ];
    }

    public function collection()
    {
        // Retornar colección vacía para generar solo los encabezados
        return Producto::where('id', 0)->get();
    }
}

