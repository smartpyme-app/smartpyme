<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Ventas\Venta;

class VentasPlantillaExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'nombre',
            'apellido',
            'fecha',
            'descripcion',
            'cantidad',
            'precio',
            'total',
            'nit',
            'nrc',
            'cod_giro',
            'nombre_comercial',
            'num_documento',
            'tipo_documento',
            'telefono',
            'correo',
            'direccion',
            'cod_departamento',
            'cod_municipio',
            'cod_distrito',
            'forma_pago',
            'condicion',
            'fecha_pago',
            'gravada',
            'subtotal',
            'iva',
            'exenta',
            'no_sujeta',
            'iva_retenido',
            'tipo_item',
        ];
    }

    public function collection()
    {
        // Retornar colección vacía para generar solo los encabezados
        return Venta::where('id', 0)->get();
    }
}

