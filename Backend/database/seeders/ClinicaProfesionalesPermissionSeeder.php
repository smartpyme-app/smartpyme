<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClinicaProfesionalesPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        'clinica.profesionales.ver',
        'clinica.profesionales.editar',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super_admin', 'admin'] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }
    }
}
