<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\FacturacionElectronica\CostaRica\BccrTipoCambio;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class CostaRicaTipoCambioServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertDatabaseHas('bccr_tipos_cambio', [
            'date' => '2026-08-04',
            'venta_reference_rate' => 510.12,
        ]);
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
        $empresa = new \App\Models\Admin\Empresa();
        $this->expectException(\RuntimeException::class);
        $svc->crcPorUsdVenta($empresa, new \DateTimeImmutable('2026-01-01'));
    }
}
