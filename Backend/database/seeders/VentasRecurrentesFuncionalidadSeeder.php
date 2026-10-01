<?php

namespace Database\Seeders;

use App\Models\Admin\Funcionalidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class VentasRecurrentesFuncionalidadSeeder extends Seeder
{
    public function run(): void
    {
        try {
            Funcionalidad::updateOrCreate(
                ['slug' => 'ventas-recurrentes-automaticas'],
                [
                    'nombre' => 'Ventas recurrentes automáticas',
                    'descripcion' => 'Genera y emite el DTE de ventas recurrentes el día de la venta plantilla',
                    'orden' => 26,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al crear funcionalidad ventas recurrentes: '.$e->getMessage());
        }
    }
};
