<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Compras\Proveedores\Proveedor;

class ProveedoresPersonasPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'Nombre',
            'Apellido',
            'DUI',
            'NIT',
            'Direccion',
            'Municipio',
            'Departamento',
            'Telefono',
            'Correo',
            'Nota',
            'Estado',
        ];
    }

    public function collection()
    {
        // Retornar colección vacía para generar solo los encabezados
        return Proveedor::where('id', 0)->get();
    }
}

