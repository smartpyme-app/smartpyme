<?php

namespace Database\Seeders;

use App\Models\DteManagement\DteTipoMapeo;
use Illuminate\Database\Seeder;

class DteTipoMapeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Maps MH DTE codes to sistema tipo_documento and destination table.
     *
     * @return void
     */
    public function run()
    {
        $mapeos = [
            ['codigo_mh' => '01', 'nombre_tipo' => 'Factura Consumidor Final', 'tipo_documento' => 'Factura', 'destino' => 'compra'],
            ['codigo_mh' => '03', 'nombre_tipo' => 'Crédito Fiscal', 'tipo_documento' => 'Crédito fiscal', 'destino' => 'compra'],
            ['codigo_mh' => '04', 'nombre_tipo' => 'Nota de Remisión', 'tipo_documento' => 'Factura', 'destino' => 'compra'],
            ['codigo_mh' => '05', 'nombre_tipo' => 'Nota de Crédito', 'tipo_documento' => 'Nota de crédito', 'destino' => 'compra'],
            ['codigo_mh' => '06', 'nombre_tipo' => 'Nota de Débito', 'tipo_documento' => 'Nota de débito', 'destino' => 'compra'],
            ['codigo_mh' => '11', 'nombre_tipo' => 'Factura de Exportación', 'tipo_documento' => 'Factura de exportación', 'destino' => 'compra'],
            ['codigo_mh' => '14', 'nombre_tipo' => 'Sustento de Gastos', 'tipo_documento' => 'Sujeto excluido', 'destino' => 'gasto'],
        ];

        foreach ($mapeos as $mapeo) {
            DteTipoMapeo::updateOrCreate(
                ['codigo_mh' => $mapeo['codigo_mh']],
                array_merge($mapeo, ['activo' => true])
            );
        }
    }
}
