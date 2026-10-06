<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Consulta;
use App\Models\Clinica\Expediente;
use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ConsultaService;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsultaServiceTest extends TestCase
{
    private ConsultaService $consultas;

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
            'id' => 1,
            'id_empresa' => 1,
            'id_usuario' => 10,
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('clinica_profesional_sucursales')->insert([
            'id_profesional' => 1,
            'id_sucursal' => 1,
        ]);

        $historial = new HistorialClinicoService();
        $expedientes = new ExpedienteService($historial);
        $this->consultas = new ConsultaService($expedientes, $historial);
    }

    public function test_crear_solo_con_campos_minimos(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $consulta = $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);

        $this->assertSame('borrador', $consulta->estado);
        $this->assertNull($consulta->anamnesis);
    }

    public function test_cerrada_no_se_reescribe_pero_acepta_addendum(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $consulta = $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);
        $this->consultas->cerrar($consulta);

        $this->expectException(ValidationException::class);
        $this->consultas->actualizar($consulta->fresh(), ['motivo' => 'Otro', 'fecha' => '2026-10-05', 'id_sucursal' => 1, 'id_usuario_profesional' => 10]);
    }

    public function test_addendum_en_consulta_cerrada(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $consulta = $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);
        $this->consultas->cerrar($consulta);
        $actualizada = $this->consultas->registrarAddendum($consulta->fresh(), 'Corrección menor');

        $this->assertStringContainsString('Corrección menor', (string) $actualizada->addendum);
    }

    public function test_profesional_fuera_de_sucursal_rechazado(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        DB::table('sucursales')->insert(['id' => 2, 'id_empresa' => 1, 'nombre' => 'Norte']);

        $this->expectException(ValidationException::class);
        $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 2,
            'id_usuario_profesional' => 10,
        ]);
    }

    public function test_paciente_inactivo_no_admite_consulta_nueva(): void
    {
        $paciente = Paciente::create([
            'id_empresa' => 1,
            'tipo' => 'HUMANO',
            'activo' => false,
            'alta_cerrada' => false,
            'nombres' => 'Inactivo',
            'apellidos' => 'Test',
            'sexo' => 'masculino',
        ]);
        Expediente::create([
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-05',
            'estado' => 'abierto',
        ]);

        $this->expectException(ValidationException::class);
        $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);
    }

    public function test_presentar_sin_permiso_clinico_oculta_anamnesis(): void
    {
        [$paciente] = $this->pacienteYExpediente();
        $consulta = $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
            'anamnesis' => 'Secreto',
        ]);

        $resumen = $this->consultas->presentar($consulta, false);
        $this->assertArrayNotHasKey('anamnesis', $resumen);
        $this->assertSame('Control', $resumen['motivo']);
    }

    public function test_cerrar_consulta_registra_evento_en_historial(): void
    {
        [$paciente] = $this->pacienteYExpediente();

        $consulta = $this->consultas->crear(1, 10, $paciente, [
            'fecha' => '2026-10-05',
            'motivo' => 'Control',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);
        $this->consultas->cerrar($consulta);

        $this->assertSame('cerrada', $consulta->fresh()->estado);
        $evento = HistorialEvento::where('origen_tipo', 'consulta')->where('origen_id', $consulta->id)->first();
        $this->assertNotNull($evento);
        $this->assertSame('consulta', $evento->tipo);
        $this->assertStringContainsString('Control', $evento->resumen);
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
            $table->integer('id_sucursal_apertura')->nullable();
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
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('motivo', 255);
            $table->string('estado', 20)->default('borrador');
            $table->text('anamnesis')->nullable();
            $table->text('antecedentes')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('indicaciones')->nullable();
            $table->json('signos_vitales')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->unsignedBigInteger('id_evento')->nullable();
            $table->text('addendum')->nullable();
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
    }

    /** @return array{0: Paciente, 1: Expediente} */
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
}
