<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/** Idempotente: solo crea el rol del portal contadores (sin tocar permisos ni otros roles). */
class AdminContadorRoleSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('constants.ROL_ADMIN_CONTADOR', 'admin_contador');

        Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
        );
    }
}
