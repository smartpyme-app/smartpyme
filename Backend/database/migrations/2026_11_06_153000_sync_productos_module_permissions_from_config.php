<?php

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use App\Models\Admin\Submodule;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * La UI de roles lee GET /permissions → Module::with(submodules.permissions).
 * Los permisos deben existir en module_permissions; Sp2339 lo hace vía seeder.
 * Esta migración sincroniza PERMISSION_PRODUCTOS (idempotente) para que aparezcan
 * ver_costos, otras entradas/salidas, etc. sin depender solo del seeder.
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

        $tree = config('permissions.PERMISSION_PRODUCTOS');
        if (! is_array($tree)) {
            return;
        }

        $this->registrarNodo($module, null, $tree);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function registrarNodo(Module $module, ?Submodule $submodule, array $tree): void
    {
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                $sub = Submodule::firstOrCreate(
                    [
                        'module_id' => $module->id,
                        'name' => (string) $key,
                    ],
                    [
                        'display_name' => ucfirst(str_replace('_', ' ', (string) $key)),
                        'description' => 'Submódulo de '.ucfirst(str_replace('_', ' ', (string) $key)),
                        'status' => 1,
                    ]
                );
                $this->registrarNodo($module, $sub, $value);

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $permission = Permission::firstOrCreate(
                ['name' => $value, 'guard_name' => 'web']
            );
            ModulePermission::firstOrCreate(
                [
                    'module_id' => $submodule ? null : $module->id,
                    'submodule_id' => $submodule?->id,
                    'permission_id' => $permission->id,
                ],
                ['permission_type' => 'base']
            );
        }
    }

    public function down(): void
    {
        // ponytail: enlaces son aditivos; no borramos module_permissions aquí.
    }
};
