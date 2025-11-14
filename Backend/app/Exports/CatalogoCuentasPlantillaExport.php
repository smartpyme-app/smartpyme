<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Contabilidad\Catalogo\Cuenta;

class CatalogoCuentasPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'codigo',
            'nombre',
            'naturaleza',
            'rubro',
            'nivel',
            'saldo',
            'abono',
            'cargo',
            'acepta_datos',
            'id_cuenta_padre',
        ];
    }

    public function collection()
    {
        // Retornar colección vacía para generar solo los encabezados
        return Cuenta::where('id', 0)->get();
    }
}

