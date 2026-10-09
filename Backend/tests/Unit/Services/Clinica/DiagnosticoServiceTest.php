<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Consulta;
use App\Models\Clinica\Diagnostico;
use App\Models\Clinica\DiagnosticoCatalogo;
use App\Models\Clinica\Expediente;
use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\DiagnosticoService;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiagnosticoServiceTest extends TestCase
{
    private DiagnosticoService $diagnosticos;

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
        $this->crearEsquemaMinimo();
        DB::table('empresas')->insert(['id' => 1]);
        DB::table('sucursales')->insert(['id' => 1, 'id_empresa' => 1, 'nombre' => 'Central']);
        DB::table('users')->insert(['id' => 10, 'id_empresa' => 1, 'name' => 'Dr. Test', 'email' => 'dr@test', 'password' => 'x']);
        DB::table('clinica_profesionales')->insert([
            'id' => 1, 'id_empresa' => 1, 'id_usuario' => 10, 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('clinica_profesional_sucursales')->insert(['id_profesional' => 1, 'id_sucursal' => 1]);

        $historial = new HistorialClinicoService();
        $this->diagnosticos = new DiagnosticoService(new ExpedienteService($historial), $historial);
    }

    public function test_crear_sin_codigo_catalogo(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $dx = $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico());

        $this->assertSame('activo', $dx->estado);
        $this->assertNull($dx->codigo);
    }

    public function test_codigo_invalido_rechazado(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $datos = $this->datosDiagnostico();
        $datos['codigo'] = 'X99';

        $this->expectException(ValidationException::class);
        $this->diagnosticos->crear(1, 10, $paciente, $datos);
    }

    public function test_solo_un_principal_por_consulta(): void
    {
        [$paciente, $expediente] = $this->pacienteYExpediente();
        $consulta = $this->consultaBorrador($paciente, $expediente);

        $primero = $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico([
            'id_consulta' => $consulta->id,
            'rol' => 'principal',
            'descripcion' => 'Principal A',
        ]));
        $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico([
            'id_consulta' => $consulta->id,
            'rol' => 'principal',
            'descripcion' => 'Principal B',
        ]));

        $this->assertSame('secundario', $primero->fresh()->rol);
        $this->assertSame(1, Diagnostico::where('id_consulta', $consulta->id)->where('rol', 'principal')->count());
    }

    public function test_cerrado_no_editable(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $dx = $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico());
        $this->diagnosticos->cerrar($dx);

        $this->expectException(ValidationException::class);
        $this->diagnosticos->actualizar($dx->fresh(), $this->datosDiagnostico(['descripcion' => 'Cambio']));
    }

    public function test_cerrar_registra_historial(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $dx = $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico());
        $this->diagnosticos->cerrar($dx);

        $evento = HistorialEvento::where('origen_tipo', 'diagnostico')->where('origen_id', $dx->id)->first();
        $this->assertNotNull($evento);
        $this->assertSame('diagnostico', $evento->tipo);
    }

    public function test_corregir_crea_nuevo_y_anula_anterior(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $dx = $this->diagnosticos->crear(1, 10, $paciente, $this->datosDiagnostico(['descripcion' => 'Original']));
        $this->diagnosticos->cerrar($dx);

        $nuevo = $this->diagnosticos->corregir(
            $dx->fresh(),
            1,
            10,
            'Error de transcripción',
            $this->datosDiagnostico(['descripcion' => 'Corregido']),
        );

        $this->assertSame('anulado', $dx->fresh()->estado);
        $this->assertSame('activo', $nuevo->estado);
        $this->assertSame('Corregido', $nuevo->descripcion);
        $this->assertSame($dx->id, $nuevo->id_diagnostico_anterior);
    }

    /** @param array<string, mixed> $extra */
    private function datosDiagnostico(array $extra = []): array
    {
        return array_merge([
            'fecha' => '2026-10-06',
            'descripcion' => 'Dermatitis',
            'rol' => 'secundario',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ], $extra);
    }

    private function pacienteYExpediente(): array
    {
        $paciente = Paciente::create([
            'id_empresa' => 1,
            'tipo' => 'HUMANO',
            'activo' => true,
            'alta_cerrada' => false,
            'nombres' => 'Ana',
            'apellidos' => 'Test',
            'sexo' => 'femenino',
        ]);
        $expediente = Expediente::create([
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-05',
            'estado' => 'abierto',
        ]);

        return [$paciente, $expediente];
    }

    private function consultaBorrador(Paciente $paciente, Expediente $expediente): Consulta
    {
        return Consulta::create([
            'id_empresa' => 1,
            'id_expediente' => $expediente->id,
            'id_paciente' => $paciente->id,
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
            'fecha' => '2026-10-06',
            'motivo' => 'Control',
            'estado' => 'borrador',
        ]);
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id');
        });
        Schema::create('sucursales', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
            $table->string('nombre')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
        });
        Schema::create('clinica_pacientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('tipo', 20);
            $table->boolean('activo')->default(true);
            $table->boolean('alta_cerrada')->default(false);
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('sexo', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('clinica_expedientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedInteger('numero');
            $table->date('fecha_apertura');
            $table->string('estado', 20);
            $table->timestamps();
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
        Schema::create('clinica_consultas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->unsignedBigInteger('id_paciente');
            $table->integer('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->date('fecha');
            $table->string('motivo', 255);
            $table->string('estado', 20)->default('borrador');
            $table->timestamps();
        });
        Schema::create('clinica_diagnostico_catalogo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('codigo', 32);
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('clinica_diagnosticos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_diagnostico_anterior')->nullable();
            $table->string('codigo', 32)->nullable();
            $table->text('descripcion');
            $table->string('rol', 16)->default('secundario');
            $table->date('fecha');
            $table->string('estado', 16)->default('activo');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('clinica_profesionales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('clinica_profesional_sucursales', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_profesional');
            $table->integer('id_sucursal');
        });

        DiagnosticoCatalogo::create([
            'id_empresa' => 1,
            'codigo' => 'L30',
            'nombre' => 'Dermatitis',
            'activo' => true,
        ]);
    }
}
