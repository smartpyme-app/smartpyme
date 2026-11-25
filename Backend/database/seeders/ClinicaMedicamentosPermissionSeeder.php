<?php

namespace Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClinicaMedicamentosPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        ClinicaPermisos::MEDICAMENTOS_VER,
        ClinicaPermisos::MEDICAMENTOS_CONFIGURAR,
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
        ] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        Role::where('name', ClinicaFase1PermissionSeeder::ROL_PROFESIONAL)->first()
            ?->givePermissionTo([ClinicaPermisos::MEDICAMENTOS_VER]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (new ClinicaModuloPermissionSeeder())->registrarCatalogo();
    }
}
