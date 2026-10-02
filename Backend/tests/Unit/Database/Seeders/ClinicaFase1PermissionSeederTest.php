<?php

namespace Tests\Unit\Database\Seeders;

use App\Services\Clinica\ClinicaPermisos;
use Database\Seeders\ClinicaFase1PermissionSeeder;
use Database\Seeders\ClinicaPacientesPermissionSeeder;
use Database\Seeders\ClinicaProfesionalesPermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicaFase1PermissionSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        config()->set('permissions', require config_path('permissions.php'));

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        foreach (['super_admin', 'admin', 'usuario_supervisor'] as $rol) {
            Role::create(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    public function test_catalogo_fase1_incluye_expediente_y_profesionales(): void
    {
        $tree = config('permissions.PERMISSION_CLINICA');
        $this->assertSame('clinica.expediente.ver', $tree['expediente']['ver']);
        $this->assertCount(4, $tree['pacientes']);
        $this->assertCount(2, $tree['profesionales']);

        $esperados = [
            ...ClinicaPacientesPermissionSeeder::PERMISOS,
            ClinicaPermisos::EXPEDIENTE_VER,
            ...ClinicaProfesionalesPermissionSeeder::PERMISOS,
        ];
        sort($esperados);
        $permisos = ClinicaFase1PermissionSeeder::PERMISOS;
        sort($permisos);
        $this->assertSame($esperados, $permisos);
    }

    public function test_seeder_es_idempotente_y_asigna_roles_de_ejemplo(): void
    {
        $this->seed(ClinicaFase1PermissionSeeder::class);
        $this->seed(ClinicaFase1PermissionSeeder::class);

        foreach (ClinicaFase1PermissionSeeder::PERMISOS as $permiso) {
            $this->assertNotNull(Permission::findByName($permiso, 'web'));
        }

        $recepcion = Role::findByName(ClinicaFase1PermissionSeeder::ROL_RECEPCION, 'web');
        $this->assertTrue($recepcion->hasPermissionTo('clinica.pacientes.crear'));
        $this->assertFalse($recepcion->hasPermissionTo(ClinicaPermisos::EXPEDIENTE_VER));
        $this->assertFalse($recepcion->hasPermissionTo('clinica.pacientes.desactivar'));

        $profesional = Role::findByName(ClinicaFase1PermissionSeeder::ROL_PROFESIONAL, 'web');
        $this->assertTrue($profesional->hasPermissionTo(ClinicaPermisos::EXPEDIENTE_VER));
        $this->assertFalse($profesional->hasPermissionTo('clinica.pacientes.crear'));

        $adminClinica = Role::findByName(ClinicaFase1PermissionSeeder::ROL_ADMINISTRACION, 'web');
        $this->assertTrue($adminClinica->hasPermissionTo('clinica.profesionales.editar'));

        $supervisor = Role::findByName('usuario_supervisor', 'web');
        $this->assertTrue($supervisor->hasPermissionTo(ClinicaPermisos::EXPEDIENTE_VER));
    }
}
