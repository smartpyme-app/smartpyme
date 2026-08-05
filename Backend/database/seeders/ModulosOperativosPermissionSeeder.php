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
 * Aditivo e idempotente. No truncar tablas ni reutilizar PermissionSeeder.
 */
class ModulosOperativosPermissionSeeder extends Seeder
{
    private const ROLES_DEFAULT = [
        'super_admin',
        'admin',
        'usuario_supervisor',
        'contador_superior',
    ];

    private const MODULES = [
        'PERMISSION_PLANILLA' => 'Planilla',
        'PERMISSION_CONSIGNAS' => 'Consignas',
        'PERMISSION_RESTAURANTE' => 'Restaurante',
        'PERMISSION_PEDIDOS' => 'Pedidos',
    ];

    public function run(): void
    {
        $allNames = [];

        foreach (self::MODULES as $configKey => $displayName) {
            $tree = config("permissions.{$configKey}");
            if (! is_array($tree)) {
                continue;
            }

            $moduleName = strtolower(str_replace('PERMISSION_', '', $configKey));
            $module = Module::firstOrCreate(
                ['name' => $moduleName],
                [
                    'display_name' => $displayName,
                    'description' => "Módulo de {$displayName}",
                    'status' => 1,
                ]
            );

            foreach ($tree as $key => $value) {
                if (is_array($value)) {
                    $submodule = Submodule::firstOrCreate(
                        [
                            'module_id' => $module->id,
                            'name' => $key,
                        ],
                        [
                            'display_name' => ucfirst(str_replace('_', ' ', $key)),
                            'description' => 'Submódulo de '.ucfirst(str_replace('_', ' ', $key)),
                            'status' => 1,
                        ]
                    );

                    foreach ($value as $permissionName) {
                        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                        ModulePermission::firstOrCreate(
                            [
                                'module_id' => null,
                                'submodule_id' => $submodule->id,
                                'permission_id' => $permission->id,
                            ],
                            ['permission_type' => 'base']
                        );
                        $allNames[] = $permissionName;
                    }

                    continue;
                }

                $permission = Permission::firstOrCreate(['name' => $value, 'guard_name' => 'web']);
                ModulePermission::firstOrCreate(
                    [
                        'module_id' => $module->id,
                        'submodule_id' => null,
                        'permission_id' => $permission->id,
                    ],
                    ['permission_type' => 'base']
                );
                $allNames[] = $value;
            }
        }

        $allNames = array_values(array_unique($allNames));

        foreach (self::ROLES_DEFAULT as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }
            $role->givePermissionTo($allNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
