<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Catálogo admin (roles/permisos) para ventas.solicitudes.* — idempotente. */
class VentasSolicitudesCompraPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $module = Module::query()->where('name', 'ventas')->first();
        if (! $module) {
            return;
        }

        $submodule = Submodule::updateOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'solicitudes',
            ],
            [
                'display_name' => 'Solicitudes de compra',
                'description' => 'Solicitudes cross-empresa en licencia',
                'status' => 1,
            ]
        );

        $cfg = config('permissions.PERMISSION_VENTAS.solicitudes');
        if (! is_array($cfg)) {
            return;
        }

        foreach ($cfg as $permissionName) {
            if (! is_string($permissionName) || $permissionName === '') {
                continue;
            }
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            ModulePermission::firstOrCreate(
                [
                    'module_id' => null,
                    'submodule_id' => $submodule->id,
                    'permission_id' => $permission->id,
                ],
                ['permission_type' => 'base']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
