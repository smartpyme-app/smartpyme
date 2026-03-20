<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Funcionalidad;
use Illuminate\Support\Facades\Log;

class RestauranteFuncionalidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Iniciando seeder de funcionalidad de módulo restaurante...');

        try {
            $funcionalidad = [
                'nombre' => 'Módulo Restaurante',
                'slug' => 'modulo-restaurante',
                'descripcion' => 'Gestión de mesas, órdenes, comandas a cocina, pre-cuentas e integración con facturación para establecimientos tipo restaurante',
                'orden' => 11
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
            Log::error("Error al crear/actualizar funcionalidad de restaurante: " . $e->getMessage());
            $this->command->error("Error al procesar la funcionalidad de restaurante: " . $e->getMessage());
        }

        $this->command->info('🎉 Seeder de funcionalidad de módulo restaurante completado');
    }
}
