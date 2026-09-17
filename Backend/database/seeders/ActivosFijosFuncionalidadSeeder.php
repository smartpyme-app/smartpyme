<?php

namespace Database\Seeders;

use App\Models\Admin\Funcionalidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ActivosFijosFuncionalidadSeeder extends Seeder
{
    public function run(): void
    {
        try {
            Funcionalidad::updateOrCreate(
                ['slug' => 'modulo-activos-fijos'],
                [
                    'nombre' => 'Módulo activos fijos',
                    'descripcion' => 'Registro, depreciación y reportes de activos fijos bajo contabilidad',
                    'orden' => 25,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al crear funcionalidad activos fijos: '.$e->getMessage());
        }
    }
}
