<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aditivo e idempotente. Crea Kardex en Productos y lo deja visible
 * en los roles que ya ven productos, para poder ocultarlo después.
 */
class KardexPermissionSeeder extends Seeder
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
                'name' => 'kardex',
            ],
            [
                'display_name' => 'Kardex',
                'description' => 'Submódulo de Kardex',
                'status' => 1,
            ]
        );

        $permission = Permission::firstOrCreate([
            'name' => 'productos.kardex.ver',
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

        $productosVer = Permission::query()->where('name', 'productos.ver')->where('guard_name', 'web')->first();
        foreach (Role::all() as $role) {
            if ($productosVer && $role->hasPermissionTo($productosVer)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
