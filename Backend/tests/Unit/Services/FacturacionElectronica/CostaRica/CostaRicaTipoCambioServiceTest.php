<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\FacturacionElectronica\CostaRica\BccrTipoCambio;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * IMPORTANTE: Estos tests NO usan RefreshDatabase ni migrate:fresh.
 * Usan SQLite :memory: solo con la tabla bccr_tipos_cambio (no tocan MySQL de .env).
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

        Schema::create('bccr_tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('venta_reference_rate', 18, 5);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rate_for_date_reads_cached_row(): void
    {
        BccrTipoCambio::query()->create([
            'date' => '2026-08-05',
            'venta_reference_rate' => 512.34567,
            'fetched_at' => now(),
        ]);

        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldNotReceive('fetchVentaRate');

        $svc = new CostaRicaTipoCambioService($client);
        $this->assertSame(512.34567, $svc->rateForDate(new \DateTimeImmutable('2026-08-05')));
    }

    public function test_rate_for_date_fetches_and_caches_when_missing(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')
            ->once()
            ->andReturn(510.12);

        $svc = new CostaRicaTipoCambioService($client);
        $rate = $svc->rateForDate(new \DateTimeImmutable('2026-08-04'));
        $this->assertSame(510.12, $rate);
        $row = BccrTipoCambio::query()->whereDate('date', '2026-08-04')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(510.12, (float) $row->venta_reference_rate, 0.00001);
    }

    public function test_rate_for_date_throws_when_bccr_unavailable(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->once()->andReturn(null);

        $svc = new CostaRicaTipoCambioService($client);
        $this->expectException(\RuntimeException::class);
        $svc->rateForDate(new \DateTimeImmutable('2026-01-01'));
    }

    public function test_crc_por_usd_venta_does_not_use_fallback_520(): void
    {
        $client = Mockery::mock(BccrTipoCambioClient::class);
        $client->shouldReceive('fetchVentaRate')->andReturn(null);
        $svc = new CostaRicaTipoCambioService($client);
        $empresa = new Empresa();
        $this->expectException(\RuntimeException::class);
        $svc->crcPorUsdVenta($empresa, new \DateTimeImmutable('2026-01-01'));
    }
}
