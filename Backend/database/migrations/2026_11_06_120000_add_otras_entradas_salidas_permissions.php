<?php

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPT-648: permisos granulares para «Otras entradas» y «Otras salidas» (además de productos.inventario.*).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $module = Module::query()->where('name', 'productos')->first();
        if (! $module) {
            return;
        }

        $defs = [
            [
                'name' => 'productos.otras_entradas_salidas.entradas.crear',
                'sub_key' => 'otras_entradas',
                'display_name' => 'Otras entradas',
            ],
            [
                'name' => 'productos.otras_entradas_salidas.salidas.crear',
                'sub_key' => 'otras_salidas',
                'display_name' => 'Otras salidas',
            ],
        ];

        foreach ($defs as $def) {
            $permission = Permission::firstOrCreate(
                ['name' => $def['name'], 'guard_name' => 'web']
            );

            $sub = Submodule::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'name' => $def['sub_key'],
                ],
                [
                    'display_name' => $def['display_name'],
                    'description' => 'Submódulo de '.$def['display_name'].' (inventario)',
                    'status' => 1,
                ]
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

        $this->otorgarDesdeInventarioCrear();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Quién ya podía crear en inventario recibe los nuevos permisos (retrocompatibilidad). */
    private function otorgarDesdeInventarioCrear(): void
    {
        $base = config('permissions.PERMISSION_PRODUCTOS.inventario.crear');
        if (! is_string($base) || $base === '') {
            return;
        }

        $basePerm = Permission::query()->where('name', $base)->where('guard_name', 'web')->first();
        if (! $basePerm) {
            return;
        }

        $nuevos = [
            'productos.otras_entradas_salidas.entradas.crear',
            'productos.otras_entradas_salidas.salidas.crear',
        ];

        foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
            if (! $role->hasPermissionTo($basePerm)) {
                continue;
            }
            foreach ($nuevos as $nombre) {
                $p = Permission::query()->where('name', $nombre)->where('guard_name', 'web')->first();
                if ($p && ! $role->hasPermissionTo($p)) {
                    $role->givePermissionTo($p);
                }
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nombres = [
            'productos.otras_entradas_salidas.entradas.crear',
            'productos.otras_entradas_salidas.salidas.crear',
        ];

        foreach ($nombres as $nombre) {
            $permission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', $nombre)
                ->first();
            if (! $permission) {
                continue;
            }
            ModulePermission::query()->where('permission_id', $permission->id)->delete();
            $permission->delete();
        }

        foreach (['otras_entradas', 'otras_salidas'] as $subName) {
            $sub = Submodule::query()
                ->whereHas('module', fn ($q) => $q->where('name', 'productos'))
                ->where('name', $subName)
                ->first();
            if ($sub && ModulePermission::query()->where('submodule_id', $sub->id)->doesntExist()) {
                $sub->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
