<?php

namespace App\Exports;

use App\Models\Proveedor;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProveedoresExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function headings():array{
        return[
            'Nombre',
            'Giro',
            'Departamento',
            'Municipio',
            'Direccion',
            'NIT',
            'NCR',
            'Telefono',
            'Contribuyente',
        ];
    }
    public function collection()
    {
        return Proveedor::where('id', 0)->get();
    }
}
