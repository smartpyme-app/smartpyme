<?php

namespace App\Exports;

use App\Models\Cliente;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientesExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    public function headings():array{
        return[
            'Nombre',
            'Apellido',
            'Direccion',
            'NIT',
            'NCR',
            'Telefono',
            'Giro',
            'Correo',
            'Departamento',
            'Contribuyente',
            'Comentarios',

        ];
    }

    public function collection()
    {
        return Cliente::where('id', 0)->get();
    }
}
