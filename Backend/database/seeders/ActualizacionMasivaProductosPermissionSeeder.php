<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ActualizacionMasivaProductosPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::firstOrCreate(
            ['name' => 'productos'],
            [
                'display_name' => 'Productos',
                'description' => 'Módulo de Productos',
                'status' => 1,
            ]
        );

        $submodule = Submodule::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'actualizacion_masiva',
            ],
            [
                'display_name' => 'Actualización masiva',
                'description' => 'Submódulo de Actualización masiva',
                'status' => 1,
            ]
        );

        $permission = Permission::firstOrCreate([
            'name' => 'productos.actualizacion_masiva.ejecutar',
            'guard_name' => 'web',
        ]);

        ModulePermission::firstOrCreate(
            [
                'module_id' => null,
                'submodule_id' => $submodule->id,
                'permission_id' => $permission->id,
            ],
            ['permission_type' => 'base']
        );

        Role::query()
            ->whereIn('name', ['admin', 'super_admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
