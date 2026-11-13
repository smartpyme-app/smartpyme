<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use App\Services\Clinica\VacunaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacunaServiceTest extends TestCase
{
    private VacunaService $vacunas;

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
        $this->vacunas = new VacunaService(new ExpedienteService($historial), $historial);
    }

    public function test_proxima_fecha_anterior_rechazada(): void
    {
        $paciente = $this->pacienteYExpediente();

        $this->expectException(ValidationException::class);
        $this->vacunas->registrar(1, 10, $paciente, [
            'nombre_vacuna' => 'Rabia',
            'fecha_aplicacion' => '2026-10-06',
            'proxima_fecha' => '2026-10-01',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);
    }

    public function test_registrar_crea_historial(): void
    {
        $paciente = $this->pacienteYExpediente();
        $aplicacion = $this->vacunas->registrar(1, 10, $paciente, [
            'nombre_vacuna' => 'Rabia',
            'fecha_aplicacion' => '2026-10-06',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
        ]);

        $this->assertSame('activa', $aplicacion->estado);
        $this->assertNotNull(HistorialEvento::where('origen_tipo', 'vacuna')->where('origen_id', $aplicacion->id)->first());
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
        DB::table('clinica_expedientes')->insert([
            'id' => 1,
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-01',
            'estado' => 'abierto',
            'created_at' => now(),
            'updated_at' => now(),
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
        Schema::create('clinica_vacuna_catalogo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 160);
            $table->string('tipo', 20)->default('vacuna');
            $table->string('tipo_paciente', 20)->nullable();
            $table->unsignedBigInteger('id_especie')->nullable();
            $table->unsignedSmallInteger('intervalo_dias')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('clinica_vacuna_aplicaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_catalogo')->nullable();
            $table->string('nombre_vacuna', 160);
            $table->string('tipo', 20)->default('vacuna');
            $table->date('fecha_aplicacion');
            $table->string('dosis', 120)->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedInteger('id_producto')->nullable();
            $table->unsignedBigInteger('id_lote')->nullable();
            $table->string('lote_numero', 80)->nullable();
            $table->date('lote_vencimiento')->nullable();
            $table->string('fabricante', 120)->nullable();
            $table->date('proxima_fecha')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('activa');
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
    }
}
