<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['auditoria.ver', 'auditoria.plataforma.ver'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::query()->where('name', config('constants.ROL_ADMIN', 'admin'))->first();
        if ($admin) {
            $admin->givePermissionTo('auditoria.ver');
        }

        $superAdmin = Role::query()->where('name', config('constants.ROL_SUPER_ADMIN', 'super_admin'))->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(['auditoria.ver', 'auditoria.plataforma.ver']);
        }

        Role::query()
            ->whereHas('permissions', fn ($p) => $p->where('name', 'organizacion.usuarios.ver'))
            ->whereHas('permissions', fn ($p) => $p->where('name', 'administracion.sucursales.ver'))
            ->each(fn (Role $role) => $role->givePermissionTo('auditoria.ver'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', ['auditoria.ver', 'auditoria.plataforma.ver'])
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
