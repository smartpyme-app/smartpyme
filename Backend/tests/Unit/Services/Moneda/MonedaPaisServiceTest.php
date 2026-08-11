<?php

namespace Tests\Unit\Services\Moneda;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Services\Moneda\BchTipoCambioClient;
use App\Services\Moneda\HondurasTipoCambioService;
use App\Services\Moneda\MonedaPaisService;
use App\Support\Admin\MonedaDefaultPorPais;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class MonedaPaisServiceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(?BchTipoCambioClient $bchClient = null): MonedaPaisService
    {
        $bccrClient = Mockery::mock(BccrTipoCambioClient::class);
        $bccrClient->shouldNotReceive('fetchVentaRate');
        $bch = $bchClient ?? Mockery::mock(BchTipoCambioClient::class);

        return new MonedaPaisService(
            new CostaRicaTipoCambioService($bccrClient),
            new HondurasTipoCambioService($bch)
        );
    }

    public function test_hn_usa_bch_y_permite_editar(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $bchClient = Mockery::mock(BchTipoCambioClient::class);
        $bchClient->shouldReceive('fetchReferenciaRate')->once()->andReturn(26.9455);

        $service = $this->makeService($bchClient);
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        $cfg = $service->configForEmpresa($empresa);
        $this->assertSame('api', $cfg['fuente']);
        $this->assertSame('bch', $cfg['api']['provider']);
        $this->assertTrue($cfg['permitir_editar']);

        $resolved = $service->resolveDocumento(
            $empresa,
            ['currency_code' => 'USD', 'total' => 100, 'iva' => 15],
            new \DateTimeImmutable('2026-08-11')
        );

        $this->assertSame('USD', $resolved['currency_code']);
        $this->assertSame(26.9455, $resolved['exchange_rate']);
        $this->assertSame(100.0, $resolved['equivalent_total']);
    }

    public function test_hn_override_manual_gana_sobre_bch(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $bchClient = Mockery::mock(BchTipoCambioClient::class);
        $bchClient->shouldNotReceive('fetchReferenciaRate');

        $service = $this->makeService($bchClient);
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        $resolved = $service->resolveDocumento(
            $empresa,
            ['currency_code' => 'USD', 'total' => 100, 'iva' => 15, 'exchange_rate' => 26.50],
            new \DateTimeImmutable('2026-08-11'),
            true
        );

        $this->assertSame(26.50, $resolved['exchange_rate']);
    }

    public function test_hn_fallback_rate_manual_si_bch_falla(): void
    {
        $cfg = MonedaDefaultPorPais::plantilla('HN');
        $cfg['rate_manual'] = 24.75;
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => $cfg,
        ]);

        $bchClient = Mockery::mock(BchTipoCambioClient::class);
        $bchClient->shouldReceive('fetchReferenciaRate')->once()->andReturn(null);

        $service = $this->makeService($bchClient);
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        $resolved = $service->resolveDocumento(
            $empresa,
            ['currency_code' => 'USD', 'total' => 100, 'iva' => 15],
            new \DateTimeImmutable('2026-08-11')
        );

        $this->assertSame(24.75, $resolved['exchange_rate']);
    }

    public function test_hn_moneda_funcional_fuerza_rate_1(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('HN'),
        ]);

        $service = $this->makeService(Mockery::mock(BchTipoCambioClient::class));
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        $resolved = $service->resolveDocumento(
            $empresa,
            ['currency_code' => 'HNL', 'total' => 100, 'iva' => 15],
            new \DateTimeImmutable('2026-08-11')
        );

        $this->assertSame('HNL', $resolved['currency_code']);
        $this->assertSame(1.0, $resolved['exchange_rate']);
    }
}
