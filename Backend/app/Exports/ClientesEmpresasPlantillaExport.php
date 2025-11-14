<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Ventas\Clientes\Cliente;

class ClientesEmpresasPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombre_empresa',
            'ncr',
            'giro',
            'tipo_contribuyente',
            'dui',
            'nit',
            'direccion',
            'departamento',
            'municipio',
            'distrito',
            'telefono',
            'correo',
        ];
    }

    public function collection()
    {
        // Retornar colección vacía para generar solo los encabezados
        return Cliente::where('id', 0)->get();
    }
}

