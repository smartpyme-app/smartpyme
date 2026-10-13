<?php

namespace Tests\Unit\Models\Admin;

use App\Models\Admin\Empresa;
use App\Models\EmpresaConfiguracion;
use App\Services\Admin\EmpresaConfiguracionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EmpresaUpdateCustomConfigMirrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.prefix' => '']);
        \DB::purge('sqlite');
        \DB::reconnect('sqlite');

        Schema::create('empresas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 255)->nullable();
            $table->string('cod_pais', 10)->nullable();
            $table->json('custom_empresa')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
            $table->unique(['empresa_id', 'pais', 'modulo']);
        });
    }

    public function test_update_custom_config_espeja_fidelizacion_completa_en_custom_empresa(): void
    {
        $empresa = Empresa::query()->create([
            'nombre' => 'Test',
            'cod_pais' => 'SV',
            'custom_empresa' => [
                'configuraciones' => [
                    'fidelizacion_completa' => false,
                    'ticket_en_pdf' => true,
                ],
            ],
        ]);

        $empresa->updateCustomConfig('configuraciones', 'fidelizacion_completa', true);

        $empresa->refresh();
        $this->assertTrue($empresa->custom_empresa['configuraciones']['fidelizacion_completa']);
        $this->assertTrue($empresa->custom_empresa['configuraciones']['ticket_en_pdf']);

        $row = EmpresaConfiguracion::query()
            ->where('empresa_id', $empresa->id)
            ->where('modulo', 'configuraciones')
            ->where('pais', 'SV')
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue($row->configuracion['fidelizacion_completa']);
    }

    public function test_update_custom_config_inicializa_seccion_en_custom_empresa_vacio(): void
    {
        $empresa = Empresa::query()->create([
            'nombre' => 'Sin legacy',
            'cod_pais' => 'SV',
            'custom_empresa' => null,
        ]);

        $empresa->updateCustomConfig('configuraciones', 'fidelizacion_enviar_correos', true);

        $empresa->refresh();
        $this->assertTrue($empresa->custom_empresa['configuraciones']['fidelizacion_enviar_correos']);

        $stored = app(EmpresaConfiguracionService::class)->get(
            (int) $empresa->id,
            'configuraciones',
            'SV'
        );
        $this->assertTrue($stored['fidelizacion_enviar_correos']);
    }
}
