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
            'tipo_cliente',
            'tipo_documento_venta',
            'correlativo',
            'estado_factura',
            'nombre',
            'apellido',
            'tipo_documento',
            'num_documento',
            'nombre_comercial',
            'nit',
            'nrc',
            'giro',
            'pais',
            'departamento',
            'municipio',
            'distrito',
            'direccion',
            'telefono',
            'correo',
            'fecha',
            'descripcion',
            'tipo_item',
            'forma_pago',
            'no_sujeta',
            'exenta',
            'gravada',
            'subtotal',
            'iva',
            'iva_retenido',
            'total',
            'condicion',
            'fecha_pago',
        ];
    }

    public function collection()
    {
        return Venta::where('id', 0)->get();
    }
}
