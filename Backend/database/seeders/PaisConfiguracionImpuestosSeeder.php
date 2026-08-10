<?php

namespace Database\Seeders;

use App\Models\PaisConfiguracion;
use App\Support\Admin\ImpuestosDefaultPorPais;
use Illuminate\Database\Seeder;

class PaisConfiguracionImpuestosSeeder extends Seeder
{
    public function run()
    {
        foreach (['SV', 'CR', 'HN', 'GT', 'NI', 'PA', 'BZ', 'MX'] as $pais) {
            PaisConfiguracion::updateOrCreate(
                [
                    'pais' => $pais,
                    'modulo' => PaisConfiguracion::MODULO_IMPUESTOS,
                ],
                [
                    'configuracion' => ImpuestosDefaultPorPais::plantilla($pais),
                ]
            );
        }
    }
}
