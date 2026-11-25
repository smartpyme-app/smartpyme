<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPT-690: solo asigna inteligencia_negocios.ver a roles gerente globales.
 * Idempotente; no requiere ejecutar RoleSeeder.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionName = config('permissions.PERMISSION_INTELIGENCIA_NEGOCIOS.ver', 'inteligencia_negocios.ver');
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => self::GUARD]);

        foreach ($this->gerenteRoleNames() as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->each(function (Role $role) use ($permissionName): void {
                    if (! $role->hasPermissionTo($permissionName)) {
                        $role->givePermissionTo($permissionName);
                    }
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionName = config('permissions.PERMISSION_INTELIGENCIA_NEGOCIOS.ver', 'inteligencia_negocios.ver');

        foreach ($this->gerenteRoleNames() as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', self::GUARD)
                ->each(function (Role $role) use ($permissionName): void {
                    if ($role->hasPermissionTo($permissionName)) {
                        $role->revokePermissionTo($permissionName);
                    }
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function gerenteRoleNames(): array
    {
        return [
            config('constants.ROL_GERENTE_VENTAS', 'gerente_ventas'),
            config('constants.ROL_GERENTE_COMPRAS', 'gerente_compras'),
            config('constants.ROL_GERENTE_OPERACIONES', 'gerente_operaciones'),
        ];
    }
};