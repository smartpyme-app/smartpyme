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
            ],
            [
                'nombre' => 'Chatbot WhatsApp',
                'slug' => 'chatbot-whatsapp',
                'descripcion' => 'Acceso al chatbot de WhatsApp',
                'orden' => 2
            ],
            [
                'nombre' => 'Cobro de Propina',
                'slug' => 'cobro-propina',
                'descripcion' => 'Permite cobrar propina en las ventas del módulo de facturación',
                'orden' => 3
            ],
            [
                'nombre' => 'Contabilidad',
                'slug' => 'contabilidad',
                'descripcion' => 'Acceso al módulo de contabilidad',
                'orden' => 4
            ],
            [
                'nombre' => 'Fidelización de Clientes',
                'slug' => 'fidelizacion-clientes',
                'descripcion' => 'Sistema de acumulación y canje de puntos para fidelizar clientes',
                'orden' => 5
            ],
            [
                'nombre' => 'Inteligencia de negocios V2',
                'slug' => 'inteligencia-negocios-v2',
                'descripcion' => 'Acceso al dashboard de inteligencia de negocios (versión 2)',
                'orden' => 6
            ],
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
