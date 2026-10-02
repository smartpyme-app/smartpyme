<?php

namespace Database\Seeders;

use App\Models\Admin\Funcionalidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ClinicaFuncionalidadSeeder extends Seeder
{
    public function run(): void
    {
        try {
            $clinica = Funcionalidad::updateOrCreate(
                ['slug' => 'clinica'],
                [
                    'nombre' => 'Clínica',
                    'descripcion' => 'Módulo clínico para clínicas humanas, veterinarias y mixtas',
                    'orden' => 30,
                    'parent_id' => null,
                ]
            );

            Funcionalidad::updateOrCreate(
                ['slug' => 'clinica-pacientes'],
                [
                    'nombre' => 'Pacientes',
                    'descripcion' => 'Padrón de pacientes humanos y animales',
                    'orden' => 31,
                    'parent_id' => $clinica->id,
                ]
            );

            Funcionalidad::updateOrCreate(
                ['slug' => 'clinica-profesionales'],
                [
                    'nombre' => 'Profesionales',
                    'descripcion' => 'Usuarios habilitados para atender en la clínica',
                    'orden' => 32,
                    'parent_id' => $clinica->id,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al crear funcionalidades de clínica: '.$e->getMessage());
        }
    }
}
