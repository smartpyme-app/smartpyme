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
        'PERMISSION_RESTAURANTE' => 'Restaurante',
        'PERMISSION_PEDIDOS' => 'Pedidos',
    ];

    /** Submódulos que dejaron de ser un módulo propio o de Gastos. */
    private const REUBICADOS = [
        ['module' => 'ventas', 'module_display' => 'Ventas', 'submodule' => 'consignas', 'submodule_display' => 'Consignas', 'config' => 'permissions.PERMISSION_VENTAS.consignas', 'origen' => 'consignas.%s'],
        ['module' => 'compras', 'module_display' => 'Compras', 'submodule' => 'consignas', 'submodule_display' => 'Consignas', 'config' => 'permissions.PERMISSION_COMPRAS.consignas', 'origen' => 'consignas.%s'],
        ['module' => 'administracion', 'module_display' => 'Administración', 'submodule' => 'departamentos', 'submodule_display' => 'Departamentos', 'config' => 'permissions.PERMISSION_ADMINISTRACION.departamentos', 'origen' => 'gastos.departamentos.%s'],
        ['module' => 'administracion', 'module_display' => 'Administración', 'submodule' => 'areas', 'submodule_display' => 'Áreas', 'config' => 'permissions.PERMISSION_ADMINISTRACION.areas', 'origen' => 'gastos.departamentos.%s'],
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

        foreach (self::REUBICADOS as $reubicado) {
            $permisos = config($reubicado['config']);
            if (! is_array($permisos)) {
                continue;
            }

            $module = Module::firstOrCreate(
                ['name' => $reubicado['module']],
                [
                    'display_name' => $reubicado['module_display'],
                    'description' => "Módulo de {$reubicado['module_display']}",
                    'status' => 1,
                ]
            );
            $submodule = Submodule::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'name' => $reubicado['submodule'],
                ],
                [
                    'display_name' => $reubicado['submodule_display'],
                    'description' => 'Submódulo de '.$reubicado['submodule_display'],
                    'status' => 1,
                ]
            );

            foreach ($permisos as $accion => $permissionName) {
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
                $this->copiarAsignacion(sprintf($reubicado['origen'], $accion), $permissionName);
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

    private function copiarAsignacion(string $origen, string $destino): void
    {
        $roles = Role::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', $origen))
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($destino);
        }
    }
}
