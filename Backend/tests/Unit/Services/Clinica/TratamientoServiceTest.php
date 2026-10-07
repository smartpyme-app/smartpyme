<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Expediente;
use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Tratamiento;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use App\Services\Clinica\TratamientoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TratamientoServiceTest extends TestCase
{
    private TratamientoService $tratamientos;

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
        $this->tratamientos = new TratamientoService(new ExpedienteService($historial), $historial);
    }

    public function test_crear_plan_y_evento_historial(): void
    {
        $paciente = $this->pacienteYExpediente();
        $plan = $this->tratamientos->crear(1, 10, $paciente, $this->datosPlan());

        $this->assertSame('indicado', $plan->estado);
        $this->assertNotNull(HistorialEvento::where('origen_tipo', 'tratamiento')->where('origen_id', $plan->id)->first());
    }

    public function test_fecha_fin_anterior_rechazada(): void
    {
        $paciente = $this->pacienteYExpediente();
        $this->expectException(ValidationException::class);
        $datos = $this->datosPlan();
        $datos['fecha_fin'] = '2020-01-01';
        $this->tratamientos->crear(1, 10, $paciente, $datos);
    }

    public function test_suspender_sin_motivo_rechazada(): void
    {
        $paciente = $this->pacienteYExpediente();
        $plan = $this->tratamientos->crear(1, 10, $paciente, $this->datosPlan());
        $this->tratamientos->iniciar($plan);

        $this->expectException(ValidationException::class);
        $this->tratamientos->suspender($plan->fresh(), '');
    }

    public function test_avance_solo_en_curso(): void
    {
        $paciente = $this->pacienteYExpediente();
        $plan = $this->tratamientos->crear(1, 10, $paciente, $this->datosPlan());

        $this->expectException(ValidationException::class);
        $this->tratamientos->registrarAvance($plan, 1, 10, ['fecha' => '2026-10-06']);
    }

    public function test_terapia_cirugia_rechazada(): void
    {
        $paciente = $this->pacienteYExpediente();
        $plan = $this->tratamientos->crear(1, 10, $paciente, $this->datosPlan());

        $this->expectException(ValidationException::class);
        $this->tratamientos->registrarTerapia($plan, 1, 10, [
            'fecha' => '2026-10-06',
            'tipo' => 'Cirugía menor',
        ]);
    }

    private function datosPlan(): array
    {
        return [
            'descripcion' => 'Rehabilitación',
            'fecha_inicio' => '2026-10-06',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
            'frecuencia' => 'Semanal',
        ];
    }

    private function pacienteYExpediente(): Paciente
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
        Expediente::create([
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-01',
            'estado' => 'abierto',
        ]);

        return $paciente;
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
        Schema::create('clinica_tratamientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->string('nombre', 160)->nullable();
            $table->text('descripcion');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('frecuencia', 120)->nullable();
            $table->string('duracion', 120)->nullable();
            $table->text('indicaciones')->nullable();
            $table->string('estado', 20)->default('indicado');
            $table->string('motivo_suspension', 255)->nullable();
            $table->string('motivo_cierre', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('clinica_tratamiento_avances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_tratamiento');
            $table->date('fecha');
            $table->text('nota')->nullable();
            $table->boolean('incumplimiento')->default(false);
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();
        });
        Schema::create('clinica_tratamiento_terapias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_tratamiento');
            $table->date('fecha');
            $table->string('tipo', 80);
            $table->text('descripcion')->nullable();
            $table->text('notas_resultado')->nullable();
            $table->unsignedBigInteger('id_usuario');
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
}
