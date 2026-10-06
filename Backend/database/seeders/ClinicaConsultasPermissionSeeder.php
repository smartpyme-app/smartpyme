<?php

namespace Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClinicaConsultasPermissionSeeder extends Seeder
{
    public const PERMISOS = [
        ClinicaPermisos::CONSULTAS_VER,
        ClinicaPermisos::CONSULTAS_CREAR,
        ClinicaPermisos::CONSULTAS_EDITAR,
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

        $catalogo = new ClinicaModuloPermissionSeeder();
        $catalogo->registrarCatalogo();
        $catalogo->asignarRolesBase();
    }
}
