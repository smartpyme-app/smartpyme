<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EstadoCuentaAntiguedadHelper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class EstadoCuentaAntiguedadHelperTest extends TestCase
{
    public function test_factura_14_tiene_208_dias_enteros_en_mas_de_120(): void
    {
        $vence = Carbon::parse('2026-02-11 09:00:00');
        $corte = Carbon::parse('2026-09-07 18:05:00');

        $dias = EstadoCuentaAntiguedadHelper::diasMora($vence, $corte);

        $this->assertSame(208, $dias);
        $this->assertSame('mas_120', EstadoCuentaAntiguedadHelper::bucket($dias));
    }

    public function test_dias_mora_cero_si_no_ha_vencido(): void
    {
        $vence = Carbon::parse('2026-09-11');
        $corte = Carbon::parse('2026-09-07 23:59:59');

        $this->assertSame(0, EstadoCuentaAntiguedadHelper::diasMora($vence, $corte));
        $this->assertSame('sin_vencer', EstadoCuentaAntiguedadHelper::bucket(0));
    }

    public function test_plazo_es_entero_absoluto(): void
    {
        $doc = Carbon::parse('2026-01-12 15:30:00');
        $vence = Carbon::parse('2026-02-11 09:00:00');

        $this->assertSame(30, EstadoCuentaAntiguedadHelper::plazoDias($doc, $vence));
    }

    public function test_buckets_incluyen_365(): void
    {
        $this->assertSame('dias_30', EstadoCuentaAntiguedadHelper::bucket(1));
        $this->assertSame('dias_30', EstadoCuentaAntiguedadHelper::bucket(30));
        $this->assertSame('dias_60', EstadoCuentaAntiguedadHelper::bucket(31));
        $this->assertSame('dias_90', EstadoCuentaAntiguedadHelper::bucket(90));
        $this->assertSame('dias_120', EstadoCuentaAntiguedadHelper::bucket(120));
        $this->assertSame('mas_120', EstadoCuentaAntiguedadHelper::bucket(121));
        $this->assertSame('mas_120', EstadoCuentaAntiguedadHelper::bucket(364));
        $this->assertSame('mas_365', EstadoCuentaAntiguedadHelper::bucket(365));
    }
}
