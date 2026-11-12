<?php

namespace Tests\Unit\Http\Controllers\Api\Compras;

use App\Http\Controllers\Api\Compras\ComprasController;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Services\Moneda\BchTipoCambioClient;
use App\Services\Moneda\HondurasTipoCambioService;
use App\Services\Moneda\MonedaPaisService;
use App\Support\Admin\MonedaDefaultPorPais;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class ComprasControllerMonedaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('pais_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
        });

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->nullable();
            $table->string('pais', 100)->nullable();
            $table->string('cod_pais', 10)->nullable();
            $table->string('moneda', 10)->nullable();
            $table->json('custom_config')->nullable();
            $table->timestamps();
        });

        Schema::create('empresa_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
        });

        Schema::create('funcionalidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100);
            $table->timestamps();
        });

        Schema::create('empresa_funcionalidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_funcionalidad');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $this->app->instance(MonedaPaisService::class, new MonedaPaisService(
            new CostaRicaTipoCambioService(Mockery::mock(BccrTipoCambioClient::class)),
            new HondurasTipoCambioService(Mockery::mock(BchTipoCambioClient::class))
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function empresaHonduras(bool $multimoneda): Empresa
    {
        $empresa = Empresa::query()->create(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        if ($multimoneda) {
            $idFuncionalidad = DB::table('funcionalidades')->insertGetId(['nombre' => 'Multimoneda', 'slug' => 'multimoneda']);
            DB::table('empresa_funcionalidades')->insert([
                'id_empresa' => $empresa->id,
                'id_funcionalidad' => $idFuncionalidad,
                'activo' => true,
            ]);
        }

        return $empresa;
    }

    private function resolver(Empresa $empresa, array $body): Compra
    {
        $compra = new Compra;
        $compra->forceFill(['id_empresa' => $empresa->id, 'fecha' => '2026-08-22', 'total' => 1000.0, 'iva' => 150.0]);

        $method = new \ReflectionMethod(ComprasController::class, 'resolverMonedaDocumento');
        $method->setAccessible(true);
        $method->invoke(new ComprasController, $compra, Request::create('/', 'POST', $body));

        return $compra;
    }

    public function test_honduras_sin_multimoneda_guarda_lempiras_no_colones(): void
    {
        $compra = $this->resolver($this->empresaHonduras(false), ['currency_code' => 'USD']);

        $this->assertSame('HNL', $compra->currency_code);
        $this->assertEquals(1.0, $compra->exchange_rate);
    }

    public function test_honduras_con_multimoneda_acepta_lempiras(): void
    {
        $compra = $this->resolver($this->empresaHonduras(true), ['currency_code' => 'HNL']);

        $this->assertSame('HNL', $compra->currency_code);
        $this->assertEquals(1000.0, $compra->equivalent_total);
    }

    public function test_honduras_usd_respeta_tipo_cambio_editado(): void
    {
        $compra = $this->resolver($this->empresaHonduras(true), ['currency_code' => 'USD', 'exchange_rate' => 26.5]);

        $this->assertSame('USD', $compra->currency_code);
        $this->assertEquals(26.5, $compra->exchange_rate);
    }
}
