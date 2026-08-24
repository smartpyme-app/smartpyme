<?php

namespace Tests\Unit\Services\Finanzas;

use App\Services\Finanzas\AntiguedadSaldosService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AntiguedadSaldosServiceTest extends TestCase
{
    private AntiguedadSaldosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AntiguedadSaldosService();
    }

    public function test_bucket_por_dias_usa_rangos_contables(): void
    {
        $this->assertSame('0_30', $this->service->bucketPorDias(0));
        $this->assertSame('0_30', $this->service->bucketPorDias(30));
        $this->assertSame('31_60', $this->service->bucketPorDias(31));
        $this->assertSame('31_60', $this->service->bucketPorDias(60));
        $this->assertSame('61_90', $this->service->bucketPorDias(61));
        $this->assertSame('61_90', $this->service->bucketPorDias(90));
        $this->assertSame('91_mas', $this->service->bucketPorDias(91));
        $this->assertSame('91_mas', $this->service->bucketPorDias(200));
    }

    public function test_saldo_documento_resta_abonos_y_devoluciones(): void
    {
        $doc = (object) [
            'total' => 100.0,
            'abonos_sum_total' => 30.0,
            'devoluciones_sum_total' => 10.0,
        ];

        $this->assertSame(60.0, $this->service->saldoDocumento($doc));
    }

    public function test_dias_antiguedad_desde_fecha_documento(): void
    {
        $corte = Carbon::parse('2026-08-24')->startOfDay();
        $this->assertSame(10, $this->service->diasAntiguedad('2026-08-14', $corte));
        $this->assertSame(0, $this->service->diasAntiguedad(null, $corte));
    }

    public function test_normalize_buckets_default_y_filtro(): void
    {
        $this->assertSame(AntiguedadSaldosService::BUCKETS, $this->service->normalizeBuckets(null));
        $this->assertSame(['0_30', '91_mas'], $this->service->normalizeBuckets(['0_30', '91_mas', 'x']));
        $this->assertSame(['31_60'], $this->service->normalizeBuckets('31_60'));
    }
}
