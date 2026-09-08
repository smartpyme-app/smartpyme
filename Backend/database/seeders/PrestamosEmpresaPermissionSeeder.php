<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PrestamosEmpresaPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        'finanzas.prestamos.ver',
        'finanzas.prestamos.crear',
        'finanzas.prestamos.pagar',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super_admin', 'admin'] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        Role::where('name', 'contador_superior')->first()?->givePermissionTo(self::PERMISOS);
        Role::where('name', 'contador_auxiliar')->first()?->givePermissionTo(['finanzas.prestamos.ver']);
    }
}
