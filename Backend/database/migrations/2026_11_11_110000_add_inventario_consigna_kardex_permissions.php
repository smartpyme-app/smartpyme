<?php

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $module = Module::query()->where('name', 'productos')->first();
        if (! $module) {
            return;
        }

        $sub = Submodule::query()->firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'consigna_kardex',
            ],
            [
                'display_name' => 'Kardex consigna',
                'description' => 'Consulta del ledger de consignaciones',
                'status' => 1,
            ]
        );

        foreach (config('permissions.PERMISSION_PRODUCTOS.consigna_kardex', []) as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            ModulePermission::firstOrCreate(
                [
                    'module_id' => null,
                    'submodule_id' => $sub->id,
                    'permission_id' => $permission->id,
                ],
                ['permission_type' => 'base']
            );
        }

        $verKardexConsigna = Permission::query()
            ->where('name', 'inventario.consigna_kardex.ver')
            ->where('guard_name', 'web')
            ->first();
        $verKardexProducto = Permission::query()
            ->where('name', 'productos.kardex.ver')
            ->where('guard_name', 'web')
            ->first();

        if ($verKardexConsigna && $verKardexProducto) {
            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if ($role->hasPermissionTo($verKardexProducto) && ! $role->hasPermissionTo($verKardexConsigna)) {
                    $role->givePermissionTo($verKardexConsigna);
                }
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_values(config('permissions.PERMISSION_PRODUCTOS.consigna_kardex', []));
        Permission::query()->whereIn('name', $names)->where('guard_name', 'web')->delete();
    }
};
