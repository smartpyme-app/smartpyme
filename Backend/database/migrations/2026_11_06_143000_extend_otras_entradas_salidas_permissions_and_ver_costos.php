<?php

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPT-648: permisos ver/editar para otras entradas/salidas; ver_costos para quien ya ve/edita inventario.
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

        $nuevosPorSub = [
            'otras_entradas' => [
                'productos.otras_entradas_salidas.entradas.ver',
                'productos.otras_entradas_salidas.entradas.editar',
            ],
            'otras_salidas' => [
                'productos.otras_entradas_salidas.salidas.ver',
                'productos.otras_entradas_salidas.salidas.editar',
            ],
        ];

        foreach ($nuevosPorSub as $subKey => $nombres) {
            $sub = Submodule::query()
                ->where('module_id', $module->id)
                ->where('name', $subKey)
                ->first();
            if (! $sub) {
                continue;
            }
            foreach ($nombres as $name) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web']
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
        }

        $crearEntrada = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.entradas.crear')
            ->where('guard_name', 'web')
            ->first();
        $verEntrada = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.entradas.ver')
            ->where('guard_name', 'web')
            ->first();
        $editarEntrada = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.entradas.editar')
            ->where('guard_name', 'web')
            ->first();

        if ($crearEntrada && $verEntrada && $editarEntrada) {
            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if (! $role->hasPermissionTo($crearEntrada)) {
                    continue;
                }
                foreach ([$verEntrada, $editarEntrada] as $p) {
                    if (! $role->hasPermissionTo($p)) {
                        $role->givePermissionTo($p);
                    }
                }
            }
        }

        $crearSalida = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.salidas.crear')
            ->where('guard_name', 'web')
            ->first();
        $verSalida = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.salidas.ver')
            ->where('guard_name', 'web')
            ->first();
        $editarSalida = Permission::query()
            ->where('name', 'productos.otras_entradas_salidas.salidas.editar')
            ->where('guard_name', 'web')
            ->first();

        if ($crearSalida && $verSalida && $editarSalida) {
            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                if (! $role->hasPermissionTo($crearSalida)) {
                    continue;
                }
                foreach ([$verSalida, $editarSalida] as $p) {
                    if (! $role->hasPermissionTo($p)) {
                        $role->givePermissionTo($p);
                    }
                }
            }
        }

        $verCostos = Permission::query()
            ->where('name', config('permissions.PERMISSION_PRODUCTOS.inventario.ver_costos'))
            ->where('guard_name', 'web')
            ->first();
        $invVer = Permission::query()
            ->where('name', config('permissions.PERMISSION_PRODUCTOS.inventario.ver'))
            ->where('guard_name', 'web')
            ->first();
        $invEditar = Permission::query()
            ->where('name', config('permissions.PERMISSION_PRODUCTOS.inventario.editar'))
            ->where('guard_name', 'web')
            ->first();

        if ($verCostos && ($invVer || $invEditar)) {
            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                $tiene = ($invVer && $role->hasPermissionTo($invVer))
                    || ($invEditar && $role->hasPermissionTo($invEditar));
                if ($tiene && ! $role->hasPermissionTo($verCostos)) {
                    $role->givePermissionTo($verCostos);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nombres = [
            'productos.otras_entradas_salidas.entradas.ver',
            'productos.otras_entradas_salidas.entradas.editar',
            'productos.otras_entradas_salidas.salidas.ver',
            'productos.otras_entradas_salidas.salidas.editar',
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
