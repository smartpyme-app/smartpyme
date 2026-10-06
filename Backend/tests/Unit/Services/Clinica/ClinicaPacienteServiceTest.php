<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Paciente;
use App\Models\User;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use App\Services\Clinica\PacienteService;
use App\Services\Clinica\ResponsableService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClinicaPacienteServiceTest extends TestCase
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id');
        });
        Schema::create('sucursales', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
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
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('clientes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
        });

        Schema::create('clinica_especies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 80);
            $table->timestamps();
        });
        Schema::create('clinica_razas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_especie');
            $table->string('nombre', 80);
            $table->timestamps();
        });
        Schema::create('clinica_pacientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->integer('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('tipo', 20);
            $table->boolean('activo')->default(true);
            $table->boolean('alta_cerrada')->default(false);
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('nombre', 120)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('documento', 50)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->text('informacion_relevante')->nullable();
            $table->unsignedBigInteger('id_especie')->nullable();
            $table->unsignedBigInteger('id_raza')->nullable();
            $table->string('color', 50)->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->string('microchip', 50)->nullable();
            $table->boolean('esterilizado')->nullable();
            $table->string('identificadores', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('clinica_expedientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_paciente');
            $table->integer('id_sucursal_apertura')->nullable();
            $table->unsignedInteger('numero');
            $table->date('fecha_apertura');
            $table->string('estado', 20)->default('abierto');
            $table->timestamps();
        });
        Schema::create('clinica_expediente_secuencias', function (Blueprint $table): void {
            $table->unsignedInteger('id_empresa')->primary();
            $table->unsignedInteger('ultimo')->default(0);
        });
        Schema::create('clinica_historial_eventos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->string('tipo', 40);
            $table->date('fecha_evento');
            $table->time('hora_evento')->nullable();
            $table->unsignedBigInteger('id_usuario_profesional')->nullable();
            $table->string('origen_tipo', 40);
            $table->unsignedBigInteger('origen_id');
            $table->string('resumen', 255);
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
            $table->unique(['origen_tipo', 'origen_id']);
        });
        Schema::create('clinica_responsables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 160);
            $table->timestamps();
        });
        Schema::create('clinica_paciente_responsables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_paciente');
            $table->integer('id_cliente')->nullable();
            $table->unsignedBigInteger('id_responsable')->nullable();
            $table->string('rol', 30);
            $table->boolean('es_principal')->default(false);
            $table->boolean('es_el_paciente')->default(false);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();
        });

        DB::table('empresas')->insert([['id' => 1], ['id' => 2]]);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    public function test_crear_humano_abre_expediente_unico_y_no_crea_cliente(): void
    {
        $usuario = $this->usuarioEmpresa(1, ['clinica.pacientes.crear', ClinicaPermisos::EXPEDIENTE_VER]);
        Auth::login($usuario);

        $expedientes = new ExpedienteService(new HistorialClinicoService());
        $servicio = new PacienteService(new ResponsableService($expedientes), $expedientes);
        $paciente = $servicio->crear(1, $usuario->id, [
            'tipo' => 'HUMANO',
            'nombres' => 'Ana',
            'apellidos' => 'López',
            'sexo' => 'femenino',
            'documento' => 'DOC-1',
            'informacion_relevante' => 'Nota clínica',
        ]);

        $this->assertSame(1, (int) DB::table('clinica_expedientes')->where('id_paciente', $paciente->id)->value('numero'));
        $this->assertSame(0, DB::table('clientes')->count());
        $this->assertSame('Nota clínica', $paciente->fresh()->informacion_relevante);

        $presentacion = $servicio->presentar($paciente->fresh());
        $this->assertSame('Nota clínica', $presentacion['informacion_relevante']);
        $this->assertSame(1, $presentacion['expediente']['numero']);
    }

    public function test_sin_permiso_expediente_oculta_cabecera_clinica_en_presentacion(): void
    {
        $expedientes = new ExpedienteService(new HistorialClinicoService());
        $servicio = new PacienteService(new ResponsableService($expedientes), $expedientes);
        Auth::login($this->usuarioEmpresa(1, ['clinica.pacientes.crear', ClinicaPermisos::EXPEDIENTE_VER]));
        $paciente = $servicio->crear(1, 1, [
            'tipo' => 'HUMANO',
            'nombres' => 'Luis',
            'apellidos' => 'Pérez',
            'sexo' => 'masculino',
            'informacion_relevante' => 'Secreto',
        ]);

        Auth::login($this->usuarioEmpresa(1, ['clinica.pacientes.ver']));
        $presentacion = $servicio->presentar($paciente);
        $this->assertNull($presentacion['expediente']);
        $this->assertNull($presentacion['informacion_relevante']);
    }

    public function test_documento_duplicado_solo_dentro_de_la_empresa(): void
    {
        $usuario = $this->usuarioEmpresa(1, ['clinica.pacientes.crear']);
        Auth::login($usuario);
        $expedientes = new ExpedienteService(new HistorialClinicoService());
        $servicio = new PacienteService(new ResponsableService($expedientes), $expedientes);

        $servicio->crear(1, $usuario->id, [
            'tipo' => 'HUMANO',
            'nombres' => 'Uno',
            'apellidos' => 'Test',
            'sexo' => 'masculino',
            'documento' => 'X-99',
        ]);

        try {
            $servicio->crear(1, $usuario->id, [
                'tipo' => 'HUMANO',
                'nombres' => 'Dos',
                'apellidos' => 'Test',
                'sexo' => 'masculino',
                'documento' => 'X-99',
            ]);
            $this->fail('Debió rechazar documento duplicado en la misma empresa.');
        } catch (ValidationException) {
            // esperado
        }

        Auth::login($this->usuarioEmpresa(2, ['clinica.pacientes.crear']));
        $otro = $servicio->crear(2, 2, [
            'tipo' => 'HUMANO',
            'nombres' => 'Otra',
            'apellidos' => 'Empresa',
            'sexo' => 'masculino',
            'documento' => 'X-99',
        ]);
        $this->assertSame(2, (int) $otro->id_empresa);
    }

    public function test_alcance_por_empresa_no_expone_paciente_ajeno(): void
    {
        $expedientes = new ExpedienteService(new HistorialClinicoService());
        $servicio = new PacienteService(new ResponsableService($expedientes), $expedientes);
        Auth::login($this->usuarioEmpresa(1, ['clinica.pacientes.crear']));
        $paciente = $servicio->crear(1, 1, [
            'tipo' => 'HUMANO',
            'nombres' => 'Privado',
            'apellidos' => 'Empresa',
            'sexo' => 'masculino',
        ]);

        Auth::login($this->usuarioEmpresa(2, ['clinica.pacientes.ver']));
        $this->assertNull(Paciente::find($paciente->id));
    }

    /** @param list<string> $permisos */
    private function usuarioEmpresa(int $idEmpresa, array $permisos): User
    {
        $id = DB::table('users')->insertGetId([
            'id_empresa' => $idEmpresa,
            'name' => 'test',
            'email' => "u{$idEmpresa}-".implode('-', $permisos).'@test.local',
            'password' => 'x',
        ]);
        $usuario = User::find($id);
        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $usuario->givePermissionTo($permiso);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $usuario->unsetRelation('permissions');

        return $usuario->fresh();
    }
}
