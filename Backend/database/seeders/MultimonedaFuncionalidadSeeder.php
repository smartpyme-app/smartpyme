<?php

namespace Database\Seeders;

use App\Models\Admin\Funcionalidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MultimonedaFuncionalidadSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Iniciando seeder de funcionalidad Multimoneda...');

        try {
            $funcionalidad = [
                'nombre' => 'Multimoneda',
                'slug' => 'multimoneda',
                'descripcion' => 'Permite registrar documentos en distintas monedas, guardando el tipo de cambio y el valor de conversión en cada transacción',
                'orden' => 11,
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
            Log::error('Error al crear/actualizar funcionalidad multimoneda: '.$e->getMessage());
            $this->command->error('Error al procesar la funcionalidad Multimoneda: '.$e->getMessage());
        }

        $this->command->info('Seeder de funcionalidad Multimoneda completado');
    }
}
