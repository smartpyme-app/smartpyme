<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Ventas\Clientes\Cliente;

class ClientesExtranjerosPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombre',
            'apellido',
            'tipo_persona',
            'tipo_documento',
            'numero_identificacion',
            'giro',
            'nombre_empresa',
            'pais',
            'direccion',
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

