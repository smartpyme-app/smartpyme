<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permiso aditivo: ver montos/total en órdenes de compra (crear/editar). */
class OrdenCompraVerTotalPermissionSeeder extends Seeder
{
    public const VER_TOTAL = 'compras.ordenes_compra.ver_total';

    public function run(): void
    {
        $module = Module::firstOrCreate(
            ['name' => 'compras'],
            [
                'display_name' => 'Compras',
                'description' => 'Módulo de Compras',
                'status' => 1,
            ]
        );

        $submodule = Submodule::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'ordenes_compra',
            ],
            [
                'display_name' => 'Órdenes de compra',
                'description' => 'Submódulo de órdenes de compra',
                'status' => 1,
            ]
        );

        $permission = Permission::firstOrCreate(['name' => self::VER_TOTAL, 'guard_name' => 'web']);
        ModulePermission::firstOrCreate(
            [
                'module_id' => null,
                'submodule_id' => $submodule->id,
                'permission_id' => $permission->id,
            ],
            ['permission_type' => 'base']
        );

        $permiso = Permission::findByName(self::VER_TOTAL, 'web');
        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo('compras.ordenes_compra.crear')
                || $role->hasPermissionTo('compras.ordenes_compra.editar')) {
                $role->givePermissionTo($permiso);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
