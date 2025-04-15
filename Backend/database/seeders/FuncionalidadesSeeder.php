<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin\Funcionalidad;
use Illuminate\Support\Facades\Log;

class FuncionalidadesSeeder extends Seeder
{

    public function run()
    {
        $this->command->info('Iniciando seeder de funcionalidades...');
        
        $funcionalidades = [
            [
                'nombre' => 'Chat Asistente IA',
                'slug' => 'chat-asistente-ia',
                'descripcion' => 'Acceso al asistente virtual con Inteligencia Artificial',
                'orden' => 1
            ]
            //,
            //Se pueden agregar mas funcionalidades con el mismo formato
        ];
        
        $contador = 0;
        
        foreach ($funcionalidades as $funcionalidad) {
            try {
                Funcionalidad::updateOrCreate(
                    ['slug' => $funcionalidad['slug']], 
                    $funcionalidad
                );
                $contador++;
            } catch (\Exception $e) {
                Log::error("Error al crear/actualizar funcionalidad {$funcionalidad['nombre']}: " . $e->getMessage());
                $this->command->error("Error al procesar la funcionalidad {$funcionalidad['nombre']}: " . $e->getMessage());
            }
        }
        
        $this->command->info("Seeder completado: {$contador} funcionalidades procesadas correctamente");
    }
}