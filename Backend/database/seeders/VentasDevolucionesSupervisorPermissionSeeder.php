<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aditivo e idempotente. Devoluciones / NC / ND para usuario supervisor en BD ya desplegadas.
 */
class VentasDevolucionesSupervisorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            config('permissions.PERMISSION_VENTAS.devoluciones.ver'),
            config('permissions.PERMISSION_VENTAS.devoluciones.crear'),
            config('permissions.PERMISSION_VENTAS.devoluciones.editar'),
            config('permissions.PERMISSION_VENTAS.devoluciones.eliminar'),
        ];

        $permissions = [];
        foreach ($names as $name) {
            $permissions[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::query()->where('name', 'usuario_supervisor')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
