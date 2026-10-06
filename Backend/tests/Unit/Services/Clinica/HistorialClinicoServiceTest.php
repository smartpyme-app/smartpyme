<?php

namespace Tests\Unit\Services\Clinica;

use App\Models\Clinica\Expediente;
use App\Models\Clinica\HistorialEvento;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\HistorialClinicoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HistorialClinicoServiceTest extends TestCase
{
    private HistorialClinicoService $historial;

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
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id');
        });
        Schema::create('clinica_pacientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('tipo', 20);
            $table->boolean('activo')->default(true);
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
        DB::table('empresas')->insert(['id' => 1]);
        $this->historial = new HistorialClinicoService();
    }

    public function test_listar_orden_cronologico_inverso_y_filtro_tipo(): void
    {
        $expediente = $this->expediente();
        HistorialEvento::create([
            'id_empresa' => 1,
            'id_expediente' => $expediente->id,
            'tipo' => 'consulta',
            'fecha_evento' => '2026-09-01',
            'origen_tipo' => 'consulta',
            'origen_id' => 1,
            'resumen' => 'Consulta vieja',
        ]);
        HistorialEvento::create([
            'id_empresa' => 1,
            'id_expediente' => $expediente->id,
            'tipo' => 'expediente_apertura',
            'fecha_evento' => '2026-10-01',
            'origen_tipo' => 'expediente',
            'origen_id' => $expediente->id,
            'resumen' => 'Apertura',
        ]);

        $lista = $this->historial->listar($expediente, [], true);
        $this->assertSame('expediente_apertura', $lista[0]['tipo']);
        $this->assertSame('consulta', $lista[1]['tipo']);

        $soloConsultas = $this->historial->listar($expediente, ['tipo' => 'consulta'], true);
        $this->assertCount(1, $soloConsultas);
        $this->assertSame('consulta', $soloConsultas[0]['tipo']);
    }

    public function test_sin_detalle_clinico_oculta_resumen_de_consulta(): void
    {
        $expediente = $this->expediente();
        HistorialEvento::create([
            'id_empresa' => 1,
            'id_expediente' => $expediente->id,
            'tipo' => 'consulta',
            'fecha_evento' => '2026-09-01',
            'origen_tipo' => 'consulta',
            'origen_id' => 5,
            'resumen' => 'Consulta: dolor abdominal',
        ]);

        $lista = $this->historial->listar($expediente, [], false);
        $this->assertSame('Evento clínico', $lista[0]['resumen']);
    }

    public function test_evento_anulado_sigue_visible(): void
    {
        $expediente = $this->expediente();
        HistorialEvento::create([
            'id_empresa' => 1,
            'id_expediente' => $expediente->id,
            'tipo' => 'consulta',
            'fecha_evento' => '2026-09-01',
            'origen_tipo' => 'consulta',
            'origen_id' => 9,
            'resumen' => 'Consulta: control',
            'estado' => 'anulado',
        ]);

        $lista = $this->historial->listar($expediente, [], true);
        $this->assertTrue($lista[0]['anulado']);
    }

    private function expediente(): Expediente
    {
        $paciente = Paciente::create([
            'id_empresa' => 1,
            'tipo' => 'HUMANO',
            'activo' => true,
        ]);

        return Expediente::create([
            'id_empresa' => 1,
            'id_paciente' => $paciente->id,
            'numero' => 1,
            'fecha_apertura' => '2026-10-01',
            'estado' => 'abierto',
        ]);
    }
}
