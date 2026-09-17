<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActivosFijosPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        'contabilidad.activos.ver',
        'contabilidad.activos.crear',
        'contabilidad.activos.capitalizar',
        'contabilidad.activos.categorias',
        'contabilidad.activos.depreciar',
        'contabilidad.activos.baja',
        'contabilidad.activos.configuracion',
    ];

    public const PERMISOS_SUPERADMIN = [
        'superadmin.activos.plantillas',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super_admin', 'admin'] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        foreach (self::PERMISOS_SUPERADMIN as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'super_admin')->first()?->givePermissionTo(self::PERMISOS_SUPERADMIN);

        Role::where('name', 'contador_superior')->first()?->givePermissionTo(self::PERMISOS);
        Role::where('name', 'contador_auxiliar')->first()?->givePermissionTo([
            'contabilidad.activos.ver',
            'contabilidad.activos.baja',
            'contabilidad.activos.configuracion',
        ]);
        Role::where('name', 'supervisor')->first()?->givePermissionTo([
            'contabilidad.activos.ver',
            'contabilidad.activos.crear',
            'contabilidad.activos.capitalizar',
            'contabilidad.activos.categorias',
            'contabilidad.activos.depreciar',
        ]);
    }
}
