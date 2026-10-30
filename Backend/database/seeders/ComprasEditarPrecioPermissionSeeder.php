<?php

namespace Database\Seeders;

use App\Models\Admin\Module;
use App\Models\Admin\ModulePermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permiso aditivo: editar costo/precio unitario en compras. */
class ComprasEditarPrecioPermissionSeeder extends Seeder
{
    public const EDITAR_PRECIO = 'compras.editar_precio';

    private const ROLES_POR_DEFECTO = [
        'super_admin',
        'admin',
        'usuario_supervisor',
    ];

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

        $permission = Permission::firstOrCreate(['name' => self::EDITAR_PRECIO, 'guard_name' => 'web']);
        ModulePermission::firstOrCreate(
            [
                'module_id' => $module->id,
                'submodule_id' => null,
                'permission_id' => $permission->id,
            ],
            ['permission_type' => 'base']
        );

        $permiso = Permission::findByName(self::EDITAR_PRECIO, 'web');
        foreach (self::ROLES_POR_DEFECTO as $roleName) {
            Role::query()->where('name', $roleName)->first()?->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
