<?php

namespace Database\Seeders;

use App\Models\DteManagement\DteTipoMapeo;
use Illuminate\Database\Seeder;

class DteTipoMapeoSeeder extends Seeder
{
    public function run()
    {
        $mapeosSv = [
            ['codigo_mh' => '01', 'nombre_tipo' => 'Factura Consumidor Final', 'tipo_documento' => 'Factura', 'destino' => 'compra'],
            ['codigo_mh' => '03', 'nombre_tipo' => 'Crédito Fiscal', 'tipo_documento' => 'Crédito fiscal', 'destino' => 'compra'],
            ['codigo_mh' => '04', 'nombre_tipo' => 'Nota de Remisión', 'tipo_documento' => 'Factura', 'destino' => 'compra'],
            ['codigo_mh' => '05', 'nombre_tipo' => 'Nota de Crédito', 'tipo_documento' => 'Nota de crédito', 'destino' => 'compra'],
            ['codigo_mh' => '06', 'nombre_tipo' => 'Nota de Débito', 'tipo_documento' => 'Nota de débito', 'destino' => 'compra'],
            ['codigo_mh' => '11', 'nombre_tipo' => 'Factura de Exportación', 'tipo_documento' => 'Factura de exportación', 'destino' => 'compra'],
            ['codigo_mh' => '14', 'nombre_tipo' => 'Sustento de Gastos', 'tipo_documento' => 'Sujeto excluido', 'destino' => 'gasto'],
        ];

        foreach ($mapeosSv as $mapeo) {
            DteTipoMapeo::updateOrCreate(
                ['cod_pais' => 'SV', 'codigo_mh' => $mapeo['codigo_mh']],
                array_merge($mapeo, ['cod_pais' => 'SV', 'activo' => true])
            );
        }

        $mapeosCr = [
            ['codigo_mh' => '01', 'nombre_tipo' => 'Factura Electrónica', 'tipo_documento' => 'Factura Electrónica', 'destino' => 'gasto'],
            ['codigo_mh' => '02', 'nombre_tipo' => 'Nota de Débito Electrónica', 'tipo_documento' => 'Nota de débito', 'destino' => 'gasto'],
            ['codigo_mh' => '03', 'nombre_tipo' => 'Nota de Crédito Electrónica', 'tipo_documento' => 'Nota de crédito', 'destino' => 'gasto'],
            ['codigo_mh' => '04', 'nombre_tipo' => 'Tiquete Electrónico', 'tipo_documento' => 'Factura', 'destino' => 'gasto'],
            ['codigo_mh' => '08', 'nombre_tipo' => 'Factura Electrónica de Compra', 'tipo_documento' => 'Factura Electrónica de Compra', 'destino' => 'gasto'],
            ['codigo_mh' => '09', 'nombre_tipo' => 'Factura Electrónica de Exportación', 'tipo_documento' => 'Factura de exportación', 'destino' => 'gasto'],
        ];

        foreach ($mapeosCr as $mapeo) {
            DteTipoMapeo::updateOrCreate(
                ['cod_pais' => 'CR', 'codigo_mh' => $mapeo['codigo_mh']],
                array_merge($mapeo, ['cod_pais' => 'CR', 'activo' => true])
            );
        }
    }
}
