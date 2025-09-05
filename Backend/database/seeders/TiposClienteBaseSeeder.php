<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TiposClienteBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();
        
        $tiposBase = [
            [
                'code' => 'STANDARD',
                'nombre' => 'Cliente Estándar',
                'descripcion' => 'Cliente regular con beneficios básicos del programa de fidelización',
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

        DB::table('tipos_cliente_base')->updateOrInsert($tiposBase, ['code']);
    }
}
