<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\ActivosDepreciacionCronService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ActivosDepreciacionCronServiceTest extends TestCase
{
    public function test_periodo_automatico_es_mes_anterior(): void
    {
        $fecha = Carbon::create(2026, 3, 1);

        $this->assertSame('2026-02', ActivosDepreciacionCronService::periodoAutomatico($fecha));
    }

    public function test_es_dia_de_corte_respeta_dia_configurado(): void
    {
        $fecha = Carbon::create(2026, 3, 15);

        $this->assertTrue(ActivosDepreciacionCronService::esDiaDeCorte(15, $fecha));
        $this->assertFalse(ActivosDepreciacionCronService::esDiaDeCorte(1, $fecha));
    }

    public function test_es_dia_de_corte_ajusta_febrero(): void
    {
        $fecha = Carbon::create(2026, 2, 28);

        $this->assertTrue(ActivosDepreciacionCronService::esDiaDeCorte(28, $fecha));
        $this->assertTrue(ActivosDepreciacionCronService::esDiaDeCorte(31, $fecha));
    }
}
