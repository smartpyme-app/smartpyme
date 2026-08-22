<?php

namespace Tests\Unit\Services\Compras\Gastos;

use App\Models\Admin\Empresa;
use App\Models\Compras\Gastos\Gasto;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Services\Moneda\BchTipoCambioClient;
use App\Services\Moneda\HondurasTipoCambioService;
use App\Services\Moneda\MonedaPaisService;
use App\Support\Admin\MonedaDefaultPorPais;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class GastoMonedaPaisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.prefix' => '']);
        \DB::purge('sqlite');
        \DB::reconnect('sqlite');

        Schema::create('pais_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
            $table->unique(['pais', 'modulo']);
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

        Schema::create('funcionalidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('empresa_funcionalidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_funcionalidad');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function bindMonedaService(?BchTipoCambioClient $bchClient = null, ?BccrTipoCambioClient $bccrClient = null): MonedaPaisService
    {
        $bccr = $bccrClient ?? Mockery::mock(BccrTipoCambioClient::class);
        $bch = $bchClient ?? Mockery::mock(BchTipoCambioClient::class);

        $service = new MonedaPaisService(
            new CostaRicaTipoCambioService($bccr),
            new HondurasTipoCambioService($bch)
        );

        $this->app->instance(MonedaPaisService::class, $service);

        return $service;
    }

    public function test_gasto_honduras_guarda_en_lempiras(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $this->bindMonedaService();

        $empresa = Empresa::query()->create([
            'pais' => 'Honduras',
            'cod_pais' => 'HN',
            'moneda' => 'HNL',
        ]);

        $service = app(MonedaPaisService::class);
        $resolved = $service->resolveDocumento(
            $empresa,
            [
                'currency_code' => 'HNL',
                'total' => 2500.50,
                'iva' => 375.08,
            ],
            Carbon::parse('2026-08-22')
        );

        $this->assertSame('HNL', $resolved['currency_code']);
        $this->assertSame(1.0, $resolved['exchange_rate']);
        $this->assertSame('2026-08-22', $resolved['exchange_rate_date']);
        $this->assertSame(2500.50, $resolved['equivalent_total']);
        $this->assertSame(375.08, $resolved['equivalent_iva']);
    }

    public function test_gasto_honduras_guarda_en_usd_con_tc_bch(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $bchClient = Mockery::mock(BchTipoCambioClient::class);
        $bchClient->shouldReceive('fetchReferenciaRate')->once()->andReturn(26.9455);

        $this->bindMonedaService($bchClient);

        $empresa = Empresa::query()->create([
            'pais' => 'Honduras',
            'cod_pais' => 'HN',
            'moneda' => 'HNL',
        ]);

        $service = app(MonedaPaisService::class);
        $resolved = $service->resolveDocumento(
            $empresa,
            [
                'currency_code' => 'USD',
                'total' => 100.0,
                'iva' => 15.0,
            ],
            Carbon::parse('2026-08-22')
        );

        $this->assertSame('USD', $resolved['currency_code']);
        $this->assertSame(26.9455, $resolved['exchange_rate']);
        $this->assertSame('2026-08-22', $resolved['exchange_rate_date']);
        $this->assertSame(100.0, $resolved['equivalent_total']);
        $this->assertSame(15.0, $resolved['equivalent_iva']);
    }

    public function test_gasto_costa_rica_guarda_en_colones(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('CR'),
        ]);

        $this->bindMonedaService();

        $empresa = Empresa::query()->create([
            'pais' => 'Costa Rica',
            'cod_pais' => 'CR',
            'moneda' => 'CRC',
        ]);

        $service = app(MonedaPaisService::class);
        $resolved = $service->resolveDocumento(
            $empresa,
            [
                'currency_code' => 'CRC',
                'total' => 50000.0,
                'iva' => 6500.0,
            ],
            Carbon::parse('2026-08-22')
        );

        $this->assertSame('CRC', $resolved['currency_code']);
        $this->assertSame(1.0, $resolved['exchange_rate']);
        $this->assertSame(50000.0, $resolved['equivalent_total']);
        $this->assertSame(6500.0, $resolved['equivalent_iva']);
    }

    public function test_gasto_el_salvador_guarda_en_usd(): void
    {
        $this->bindMonedaService();

        $empresa = Empresa::query()->create([
            'pais' => 'El Salvador',
            'cod_pais' => 'SV',
            'moneda' => 'USD',
        ]);

        $service = app(MonedaPaisService::class);
        $resolved = $service->resolveDocumento(
            $empresa,
            [
                'currency_code' => 'USD',
                'total' => 150.0,
                'iva' => 19.5,
            ],
            Carbon::parse('2026-08-22')
        );

        $this->assertSame('USD', $resolved['currency_code']);
        $this->assertSame(1.0, $resolved['exchange_rate']);
        $this->assertSame(150.0, $resolved['equivalent_total']);
        $this->assertSame(19.5, $resolved['equivalent_iva']);
    }
}
