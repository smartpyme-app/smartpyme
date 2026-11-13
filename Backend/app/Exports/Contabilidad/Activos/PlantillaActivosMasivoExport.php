<?php

namespace App\Exports\Contabilidad\Activos;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlantillaActivosMasivoExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'referencia',
            'nombre',
            'nombre_categoria',
            'fecha_compra',
            'valor_compra',
            'vida_util',
            'valor_residual',
            'fecha_inicio_depreciacion',
            'depreciacion_acumulada_inicial',
            'numero_de_serie',
            'ubicacion',
            'es_usado',
            'porcentaje_base_usado',
            'descripcion',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                'AF-EJ-001',
                'Laptop contabilidad',
                'Equipo de cómputo',
                '2024-01-15',
                1200,
                '',
                0,
                '2024-01-15',
                300,
                'SN-12345',
                'Oficina central',
                0,
                '',
                'Ejemplo — elimine esta fila al importar',
            ],
        ]);
    }
}
