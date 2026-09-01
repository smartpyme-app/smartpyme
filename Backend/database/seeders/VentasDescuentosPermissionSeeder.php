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
class VentasDescuentosPermissionSeeder extends Seeder
{
    private const APLICAR = 'ventas.descuentos.aplicar';

    private const AUTORIZAR = 'ventas.descuentos.autorizar';

    private const ROLES_AUTORIZAR = [
        'super_admin',
        'admin',
        'usuario_supervisor',
        'gerente_ventas',
    ];

    public function run(): void
    {
        $module = Module::firstOrCreate(
            ['name' => 'ventas'],
            [
                'display_name' => 'Ventas',
                'description' => 'Módulo de Ventas',
                'status' => 1,
            ]
        );

        $submodule = Submodule::firstOrCreate(
            [
                'module_id' => $module->id,
                'name' => 'descuentos',
            ],
            [
                'display_name' => 'Descuentos',
                'description' => 'Submódulo de Descuentos',
                'status' => 1,
            ]
        );

        foreach ([self::APLICAR, self::AUTORIZAR] as $permissionName) {
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

        $aplicar = Permission::findByName(self::APLICAR, 'web');
        foreach (Role::all() as $role) {
            if ($role->hasPermissionTo('ventas.registros.crear')) {
                $role->givePermissionTo($aplicar);
            }
        }

        $autorizar = Permission::findByName(self::AUTORIZAR, 'web');
        foreach (self::ROLES_AUTORIZAR as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();
            if (! $role) {
                continue;
            }
            $role->givePermissionTo($autorizar);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
