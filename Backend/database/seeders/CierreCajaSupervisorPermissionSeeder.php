<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aditivo e idempotente. Asigna el cierre de caja al supervisor sin rehacer el catálogo.
 */
class CierreCajaSupervisorPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => config('permissions.PERMISSION_FINANZAS.cierre_caja.ver'),
            'guard_name' => 'web',
        ]);

        $role = Role::query()->where('name', 'usuario_supervisor')->first();
        if ($role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
