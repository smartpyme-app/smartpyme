<?php

namespace Database\Seeders\FidelizacionCliente;


use Illuminate\Database\Seeder;
use App\Models\FidelizacionClientes\TipoClienteBase;
use Carbon\Carbon;

class TipoClienteBaseSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        
        $tiposBase = [
            [
                'code' => 'STANDARD',
                'nombre' => 'Cliente Estándar',
                'descripcion' => 'Cliente regular con beneficios básicos del programa de Lealtad',
                'orden' => 1,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'VIP',
                'nombre' => 'Cliente VIP',
                'descripcion' => 'Cliente con beneficios premium y mayores recompensas',
                'orden' => 2,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ULTRAVIP',
                'nombre' => 'Cliente Ultra VIP',
                'descripcion' => 'Cliente con máximos beneficios y acceso exclusivo',
                'orden' => 3,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($tiposBase as $tipo) {
            TipoClienteBase::firstOrCreate(
                ['code' => $tipo['code']],
                $tipo
            );
        }

        $this->command->info('✅ Tipos de cliente base creados correctamente.');
    }
}