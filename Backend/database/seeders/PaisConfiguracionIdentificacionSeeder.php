<?php

namespace Database\Seeders;

use App\Models\PaisConfiguracion;
use App\Support\Admin\IdentificacionDefaultPorPais;
use Illuminate\Database\Seeder;

class PaisConfiguracionIdentificacionSeeder extends Seeder
{
    public function run()
    {
        foreach (['SV', 'CR', 'HN'] as $pais) {
            PaisConfiguracion::updateOrCreate(
                [
                    'pais' => $pais,
                    'modulo' => PaisConfiguracion::MODULO_IDENTIFICACION,
                ],
                [
                    'configuracion' => IdentificacionDefaultPorPais::plantilla($pais),
                ]
            );
        }
    }
}
