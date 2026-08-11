<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Support\Admin\MonedaDefaultPorPais;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * IMPORTANTE: Estos tests NO usan RefreshDatabase ni migrate:fresh.
 * Usan SQLite :memory: solo con pais_configuracion.
 */
final class CostaRicaTipoCambioServiceTest extends TestCase
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

        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00', 'America/Costa_Rica'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_rate_for_date_reads_cached_rate_del_dia(): void
    {
        $cfg = MonedaDefaultPorPais::plantilla('CR');
        $cfg['rate_del_dia'] = [
            'date' => '2026-08-05',
            'from' => 'USD',
            'to' => 'CRC',
            'rate' => 512.34567,
            'fetched_at' => now()->toIso8601String(),
        ];
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => $cfg,
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldNotReceive('fetchVentaRate');

        $svc = new CostaRicaTipoCambioService($client);
        $this->assertSame(512.34567, $svc->rateForDate(new \DateTimeImmutable('2026-08-05')));
    }

    public function test_rate_for_today_fetches_and_caches_in_pais_configuracion(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('CR'),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')
            ->once()
            ->andReturn(510.12);

        $svc = new CostaRicaTipoCambioService($client);
        $rate = $svc->rateForDate(new \DateTimeImmutable('2026-08-05'));
        $this->assertSame(510.12, $rate);

        $row = PaisConfiguracion::query()->pais('CR')->modulo(PaisConfiguracion::MODULO_MONEDA)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-08-05', $row->configuracion['rate_del_dia']['date'] ?? null);
        $this->assertEqualsWithDelta(510.12, (float) $row->configuracion['rate_del_dia']['rate'], 0.00001);
    }

    public function test_rate_for_past_date_fetches_without_writing_cache(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('CR'),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')
            ->once()
            ->andReturn(500.5);

        $svc = new CostaRicaTipoCambioService($client);
        $this->assertSame(500.5, $svc->rateForDate(new \DateTimeImmutable('2026-08-01')));

        $row = PaisConfiguracion::query()->pais('CR')->modulo(PaisConfiguracion::MODULO_MONEDA)->first();
        $this->assertNull($row->configuracion['rate_del_dia'] ?? null);
    }

    public function test_rate_for_date_falls_back_to_rate_manual(): void
    {
        $cfg = MonedaDefaultPorPais::plantilla('CR');
        $cfg['rate_manual'] = 511.11;
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => $cfg,
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->once()->andReturn(null);

        $svc = new CostaRicaTipoCambioService($client);
        $this->assertSame(511.11, $svc->rateForDate(new \DateTimeImmutable('2026-08-05')));
    }

    public function test_rate_for_date_throws_when_bccr_unavailable_and_no_manual(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('CR'),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->once()->andReturn(null);

        $svc = new CostaRicaTipoCambioService($client);
        $this->expectException(\RuntimeException::class);
        $svc->rateForDate(new \DateTimeImmutable('2026-01-01'));
    }

    public function test_crc_por_usd_venta_does_not_use_fallback_520(): void
    {
        PaisConfiguracion::query()->create([
            'pais' => 'CR',
            'modulo' => PaisConfiguracion::MODULO_MONEDA,
            'configuracion' => MonedaDefaultPorPais::plantilla('CR'),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->andReturn(null);
        $svc = new CostaRicaTipoCambioService($client);
        $empresa = new Empresa();
        $this->expectException(\RuntimeException::class);
        $svc->crcPorUsdVenta($empresa, new \DateTimeImmutable('2026-01-01'));
    }
}
