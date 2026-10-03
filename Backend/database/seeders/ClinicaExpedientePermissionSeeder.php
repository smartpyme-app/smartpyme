<?php

namespace Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Fase 2 (SP-795): permiso de archivar/reabrir expediente. Aditivo e idempotente. */
class ClinicaExpedientePermissionSeeder extends Seeder
{
    public const PERMISOS = [
        ClinicaPermisos::EXPEDIENTE_ARCHIVAR,
    ];

    private const ROLES_CON_ARCHIVO = [
        'super_admin',
        'admin',
        'usuario_supervisor',
        ClinicaFase1PermissionSeeder::ROL_ADMINISTRACION,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES_CON_ARCHIVO as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
