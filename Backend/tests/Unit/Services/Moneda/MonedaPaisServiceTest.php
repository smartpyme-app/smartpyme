<?php

namespace Tests\Unit\Services\Moneda;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
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

    public function test_hn_manual_usa_rate_manual_sin_llamar_bccr(): void
    {
        $cfg = MonedaDefaultPorPais::plantilla('HN');
        $cfg['rate_manual'] = 24.75;
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => $cfg,
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldNotReceive('fetchVentaRate');

        $service = new MonedaPaisService(new CostaRicaTipoCambioService($client));
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN', 'moneda' => 'HNL']);

        $resolved = $service->resolveDocumento(
            $empresa,
            ['currency_code' => 'USD', 'total' => 100, 'iva' => 15],
            new \DateTimeImmutable('2026-08-11')
        );

        $this->assertSame('USD', $resolved['currency_code']);
        $this->assertSame(24.75, $resolved['exchange_rate']);
        $this->assertSame(100.0, $resolved['equivalent_total']);
    }

    public function test_hn_moneda_funcional_fuerza_rate_1(): void
    {
        $cfg = MonedaDefaultPorPais::plantilla('HN');
        $cfg['rate_manual'] = 24.75;
        PaisConfiguracion::query()->create([
            'pais' => 'HN',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => $cfg,
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $service = new MonedaPaisService(new CostaRicaTipoCambioService($client));
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
