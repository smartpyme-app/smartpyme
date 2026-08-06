<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
            'marca',
            'impuesto',
            'genera_comanda',
            'destino_comanda',
        ];
    }

    public function collection()
    {
        // Solo encabezados: no tocar BD (evita scopes de empresa / auth en la plantilla).
        return new Collection([]);
    }
}

