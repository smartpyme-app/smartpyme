<?php

namespace Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fase 1 clínica: catálogo, roles de ejemplo e idempotencia (no ejecutar PermissionSeeder).
 */
class ClinicaFase1PermissionSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISOS_PACIENTES = ClinicaPacientesPermissionSeeder::PERMISOS;

    /** @var list<string> */
    public const PERMISOS_PROFESIONALES = ClinicaProfesionalesPermissionSeeder::PERMISOS;

    /** @var list<string> */
    public const PERMISOS = [
        ...self::PERMISOS_PACIENTES,
        ClinicaPermisos::EXPEDIENTE_VER,
        ...self::PERMISOS_PROFESIONALES,
    ];

    public const ROL_ADMINISTRACION = 'clinica_administracion';

    public const ROL_RECEPCION = 'clinica_recepcion';

    public const ROL_PROFESIONAL = 'clinica_profesional';

    /** Roles globales que reciben todo el catálogo clínico al actualizar instalaciones. */
    private const ROLES_DEFAULT = [
        'super_admin',
        'admin',
        'usuario_supervisor',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES_DEFAULT as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo(self::PERMISOS);
        }

        $administracion = Role::firstOrCreate(['name' => self::ROL_ADMINISTRACION, 'guard_name' => 'web']);
        $administracion->syncPermissions(self::PERMISOS);

        $recepcion = Role::firstOrCreate(['name' => self::ROL_RECEPCION, 'guard_name' => 'web']);
        $recepcion->syncPermissions([
            'clinica.pacientes.ver',
            'clinica.pacientes.crear',
            'clinica.pacientes.editar',
        ]);

        $profesional = Role::firstOrCreate(['name' => self::ROL_PROFESIONAL, 'guard_name' => 'web']);
        $profesional->syncPermissions([
            'clinica.pacientes.ver',
            ClinicaPermisos::EXPEDIENTE_VER,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->call(ClinicaModuloPermissionSeeder::class);
    }
}
