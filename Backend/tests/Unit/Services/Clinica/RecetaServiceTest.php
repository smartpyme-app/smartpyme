<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use App\Services\Clinica\RecetaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecetaServiceTest extends TestCase
{
    private RecetaService $recetas;

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
        $this->recetas = new RecetaService(new ExpedienteService($historial), $historial);
    }

    public function test_emitir_exige_dosis_en_lineas(): void
    {
        $paciente = $this->pacienteYExpediente();
        $receta = $this->recetas->crear(1, 10, $paciente, $this->datosReceta(['dosis' => '']));

        $this->expectException(ValidationException::class);
        $this->recetas->emitir($receta);
    }

    public function test_emitir_registra_historial(): void
    {
        $paciente = $this->pacienteYExpediente();
        $receta = $this->recetas->crear(1, 10, $paciente, $this->datosReceta());
        $emitida = $this->recetas->emitir($receta);

        $this->assertSame('emitida', $emitida->estado);
        $this->assertNotNull(HistorialEvento::where('origen_tipo', 'receta')->where('origen_id', $emitida->id)->first());
    }

    /** @param array<string, mixed> $overrideLinea */
    private function datosReceta(array $overrideLinea = []): array
    {
        return [
            'fecha' => '2026-10-06',
            'id_sucursal' => 1,
            'id_usuario_profesional' => 10,
            'lineas' => [[
                'nombre_medicamento' => 'Amoxicilina',
                'dosis' => '500 mg',
                ...$overrideLinea,
            ]],
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
        Schema::create('clinica_recetas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_expediente');
            $table->unsignedBigInteger('id_paciente');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedBigInteger('id_tratamiento')->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->date('fecha');
            $table->string('estado', 20)->default('borrador');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
        Schema::create('clinica_receta_lineas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_receta');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->unsignedInteger('id_producto')->nullable();
            $table->string('nombre_medicamento', 255);
            $table->string('dosis', 120);
            $table->string('frecuencia', 120)->nullable();
            $table->string('duracion', 120)->nullable();
            $table->string('via', 80)->nullable();
            $table->text('indicaciones')->nullable();
            $table->decimal('peso_referencia', 10, 2)->nullable();
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
