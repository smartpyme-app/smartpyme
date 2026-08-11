<?php

namespace Database\Seeders;

use App\Models\PaisConfiguracion;
use App\Support\Admin\MonedaDefaultPorPais;
use Illuminate\Database\Seeder;

class PaisConfiguracionMonedaSeeder extends Seeder
{
    public function run()
    {
        foreach (['CR', 'HN'] as $pais) {
            PaisConfiguracion::updateOrCreate(
                [
                    'pais' => $pais,
                    'modulo' => PaisConfiguracion::MODULO_MONEDA,
                ],
                [
                    'configuracion' => MonedaDefaultPorPais::plantilla($pais),
                ]
            );
        }
    }
}
