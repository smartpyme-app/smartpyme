<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Plan::create([
            'nombre' => 'Emprendedor',
            'slug' => 'emprendedor',
            'descripcion' => 'Plan para emprendedores',
            'precio' => 16.95,
            'duracion_dias' => 30,
            'activo' => true,
            'enlace_n1co' => 'https://pay.n1co.shop/pl/WEwwXTOpy',
            'caracteristicas' => null,
            'id_enlace_pago_n1co' => null,
            'n1co_metadata' => null,
            'permite_periodo_prueba' => true,
            'dias_periodo_prueba' => 3,
        ]);

        Plan::create([
            'nombre' => 'Estándar',
            'slug' => 'estandar',
            'descripcion' => 'Plan para empresas estándar',
            'precio' => 28.25,
            'duracion_dias' => 30,
            'activo' => true,
            'enlace_n1co' => 'https://pay.n1co.shop/pl/yX99lF1Dl',
            'caracteristicas' => null,
            'id_enlace_pago_n1co' => null,
            'n1co_metadata' => null,
            'permite_periodo_prueba' => true,
            'dias_periodo_prueba' => 3,
        ]);

        Plan::create([
            'nombre' => 'Avanzado',
            'slug' => 'avanzado',
            'descripcion' => 'Plan para empresas avanzadas',
            'precio' => 56.50,
            'duracion_dias' => 30,
            'activo' => true,
            'enlace_n1co' => 'https://pay.n1co.shop/pl/vbj8Rh0y1',
            'caracteristicas' => null,
            'id_enlace_pago_n1co' => null,
            'n1co_metadata' => null,
            'permite_periodo_prueba' => true,
            'dias_periodo_prueba' => 3,
        ]);

        Plan::create([
            'nombre' => 'Pro',
            'slug' => 'pro',
            'descripcion' => 'Plan para empresas profesionales',
            'precio' => 113.00,
            'duracion_dias' => 30,
            'activo' => true,
            'enlace_n1co' => 'https://pay.n1co.shop/pl/vbj8Rh0y1',
            'caracteristicas' => null,
            'id_enlace_pago_n1co' => null,
            'n1co_metadata' => null,
            'permite_periodo_prueba' => true,
            'dias_periodo_prueba' => 3,
        ]);
    }
}
