<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Funcionalidad;
use Illuminate\Support\Facades\Log;

class FidelizacionFuncionalidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Iniciando seeder de funcionalidad de fidelización...');

        try {
            $funcionalidad = [
                'nombre' => 'Fidelización de Clientes',
                'slug' => 'fidelizacion-clientes',
                'descripcion' => 'Sistema de acumulación y canje de puntos para fidelizar clientes',
                'orden' => 10
            ];

            $funcionalidadCreada = Funcionalidad::updateOrCreate(
                ['slug' => $funcionalidad['slug']],
                $funcionalidad
            );

            if ($funcionalidadCreada->wasRecentlyCreated) {
                $this->command->info("✅ Funcionalidad '{$funcionalidad['nombre']}' creada correctamente");
            } else {
                $this->command->info("ℹ️ Funcionalidad '{$funcionalidad['nombre']}' ya existía, actualizada");
            }

        } catch (\Exception $e) {
            Log::error("Error al crear/actualizar funcionalidad de fidelización: " . $e->getMessage());
            $this->command->error("Error al procesar la funcionalidad de fidelización: " . $e->getMessage());
        }

        $this->command->info('🎉 Seeder de funcionalidad de fidelización completado');
    }
}
