<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotente: crea el rol ventas_limitado y copia permisos de usuario_ventas si aún no tiene. */
class VentasLimitadoRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name' => config('constants.ROL_VENTAS_LIMITADO', 'ventas_limitado'),
            'guard_name' => 'web',
        ]);

        if ($role->permissions()->count() > 0) {
            return;
        }

        $ventas = Role::query()
            ->where('name', config('constants.ROL_USUARIO_VENTAS', 'usuario_ventas'))
            ->where('guard_name', 'web')
            ->first();

        $copiados = $ventas?->permissions->pluck('name') ?? collect();
        if ($copiados->isNotEmpty()) {
            $role->syncPermissions($copiados->all());
            return;
        }

        $existentes = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::permisosVentasSinAutorizar())
            ->pluck('name');

        if ($existentes->isNotEmpty()) {
            $role->syncPermissions($existentes->all());
        }
    }

    /** Mismos permisos de ventas que usuario_ventas, sin autorizar descuentos. */
    public static function permisosVentasSinAutorizar(): array
    {
        $nombres = [];
        foreach (config('permissions.PERMISSION_VENTAS', []) as $value) {
            foreach (is_array($value) ? $value : [$value] as $nombre) {
                if ($nombre !== 'ventas.descuentos.autorizar') {
                    $nombres[] = $nombre;
                }
            }
        }

        return $nombres;
    }
}
