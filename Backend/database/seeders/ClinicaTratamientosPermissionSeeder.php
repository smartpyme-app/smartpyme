<?php

namespace Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClinicaTratamientosPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        ClinicaPermisos::TRATAMIENTOS_VER,
        ClinicaPermisos::TRATAMIENTOS_CREAR,
        ClinicaPermisos::TRATAMIENTOS_EDITAR,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach ([
            'super_admin',
            'admin',
            'usuario_supervisor',
            ClinicaFase1PermissionSeeder::ROL_ADMINISTRACION,
            ClinicaFase1PermissionSeeder::ROL_PROFESIONAL,
        ] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (new ClinicaModuloPermissionSeeder())->registrarCatalogo();
    }
}
