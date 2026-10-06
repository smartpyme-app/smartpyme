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
 * Catálogo admin (módulo Clínica en roles/permisos) + sync Spatie en roles base.
 * Aditivo e idempotente.
 */
class ClinicaModuloPermissionSeeder extends Seeder
{
    private const SUBMODULE_LABELS = [
        'pacientes' => 'Pacientes',
        'expediente' => 'Expediente clínico',
        'profesionales' => 'Profesionales',
        'consultas' => 'Consultas clínicas',
    ];

    /** @return list<string> */
    public static function nombresPermisos(): array
    {
        $tree = config('permissions.PERMISSION_CLINICA');
        if (! is_array($tree)) {
            return ClinicaFase1PermissionSeeder::PERMISOS;
        }

        $nombres = [];
        foreach ($tree as $acciones) {
            if (! is_array($acciones)) {
                continue;
            }
            foreach ($acciones as $nombre) {
                if (is_string($nombre)) {
                    $nombres[] = $nombre;
                }
            }
        }

        return array_values(array_unique($nombres));
    }

    public function run(): void
    {
        $this->registrarCatalogo();
        $this->asignarRolesBase();
    }

    public function registrarCatalogo(): void
    {
        $tree = config('permissions.PERMISSION_CLINICA');
        if (! is_array($tree)) {
            return;
        }

        $module = Module::firstOrCreate(
            ['name' => 'clinica'],
            [
                'display_name' => 'Clínica',
                'description' => 'Módulo clínico',
                'status' => 1,
            ]
        );

        foreach ($tree as $subName => $acciones) {
            if (! is_array($acciones)) {
                continue;
            }

            $label = self::SUBMODULE_LABELS[$subName] ?? ucfirst(str_replace('_', ' ', $subName));
            $submodule = Submodule::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'name' => $subName,
                ],
                [
                    'display_name' => $label,
                    'description' => 'Submódulo de '.$label,
                    'status' => 1,
                ]
            );

            foreach ($acciones as $permissionName) {
                if (! is_string($permissionName)) {
                    continue;
                }
                $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
                ModulePermission::firstOrCreate(
                    [
                        'module_id' => null,
                        'submodule_id' => $submodule->id,
                        'permission_id' => $permission->id,
                    ],
                    ['permission_type' => 'base']
                );
            }
        }
    }

    public function asignarRolesBase(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nombres = self::nombresPermisos();
        foreach ($nombres as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ([
            'super_admin',
            'admin',
            'usuario_supervisor',
            ClinicaFase1PermissionSeeder::ROL_ADMINISTRACION,
        ] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo($nombres);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
