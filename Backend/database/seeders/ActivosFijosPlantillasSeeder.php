<?php

namespace Database\Seeders;

use App\Models\Contabilidad\PaisActivoCategoriaPlantilla;
use Illuminate\Database\Seeder;

class ActivosFijosPlantillasSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            ['nombre' => 'Equipo de cómputo', 'porcentaje_anual' => 25, 'vida_util_anios' => 4],
            ['nombre' => 'Mobiliario y enseres', 'porcentaje_anual' => 10, 'vida_util_anios' => 10],
            ['nombre' => 'Maquinaria', 'porcentaje_anual' => 15, 'vida_util_anios' => 6.67, 'permite_bien_usado' => true],
            ['nombre' => 'Vehículos', 'porcentaje_anual' => 20, 'vida_util_anios' => 5, 'permite_bien_usado' => true],
            ['nombre' => 'Edificios e instalaciones', 'porcentaje_anual' => 5, 'vida_util_anios' => 20],
        ];

        foreach ($plantillas as $row) {
            PaisActivoCategoriaPlantilla::updateOrCreate(
                ['cod_pais' => 'SV', 'nombre' => $row['nombre']],
                array_merge([
                    'metodo_depreciacion' => 'linea_recta',
                    'valor_residual_default' => 0,
                    'permite_bien_usado' => false,
                    'activo' => true,
                ], $row)
            );
        }
    }
}
