<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Expediente;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ExpedienteService;
use App\Services\Clinica\HistorialClinicoService;
use App\Services\Clinica\ResponsableService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpedienteServiceTest extends TestCase
{
    private ExpedienteService $expedientes;

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
        $this->expedientes = new ExpedienteService(new HistorialClinicoService());
    }

    public function test_archivar_y_reabrir_cambia_estado(): void
    {
        $paciente = $this->pacienteConExpediente('abierto');
        $expediente = Expediente::where('id_paciente', $paciente->id)->first();

        $this->expedientes->cambiarEstado($expediente, 'archivado');
        $this->assertSame('archivado', $expediente->fresh()->estado);
        $this->assertFalse($this->expedientes->operativo($expediente->fresh()));

        $this->expedientes->cambiarEstado($expediente->fresh(), 'abierto');
        $this->assertTrue($this->expedientes->operativo($expediente->fresh()));
    }

    public function test_expediente_archivado_bloquea_responsables(): void
    {
        $paciente = $this->pacienteConExpediente('archivado');
        $responsables = new ResponsableService($this->expedientes);

        $this->expectException(ValidationException::class);
        $responsables->vincular($paciente, [
            'rol' => 'principal',
            'nombre' => 'Tutor Test',
        ]);
    }

    public function test_presentar_incluye_secciones_placeholder(): void
    {
        $paciente = $this->pacienteConExpediente('abierto');
        $expediente = Expediente::where('id_paciente', $paciente->id)->first();
        $data = $this->expedientes->presentar($expediente);

        $this->assertSame(count(ExpedienteService::SECCIONES), count($data['secciones']));
        $this->assertTrue($data['secciones'][0]['disponible']);
        $this->assertSame('historial', $data['secciones'][0]['slug']);
        $this->assertFalse($data['secciones'][2]['disponible']);
    }

    private function pacienteConExpediente(string $estado): Paciente
    {
        $paciente = Paciente::create([
            'id_empresa' => 1,
            'tipo' => 'HUMANO',
            'activo' => true,
            'alta_cerrada' => false,
            'nombres' => 'Test',
            'apellidos' => 'Expediente',
            'sexo' => 'masculino',
        ]);
        Expediente::create([
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-02',
            'estado' => $estado,
        ]);

        return $paciente;
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id');
        });
        Schema::create('clinica_pacientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('tipo', 20);
            $table->boolean('activo')->default(true);
            $table->boolean('alta_cerrada')->default(false);
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('nombre', 120)->nullable();
            $table->date('fecha_nacimiento')->nullable();
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
        Schema::create('clientes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
        });
    }
}
