<?php

namespace Database\Seeders;

use App\Models\PaisConfiguracion;
use App\Services\Planilla\PlanillaTemplatesService;
use Illuminate\Database\Seeder;

class PaisConfiguracionPlanillasSeeder extends Seeder
{
    public function run()
    {
        foreach (['SV', 'GT', 'HN', 'NI', 'CR', 'PA'] as $pais) {
            PaisConfiguracion::updateOrCreate(
                [
                    'pais' => $pais,
                    'modulo' => PaisConfiguracion::MODULO_PLANILLAS,
                ],
                [
                    'configuracion' => PlanillaTemplatesService::plantilla($pais),
                ]
            );
        }
    }
}
