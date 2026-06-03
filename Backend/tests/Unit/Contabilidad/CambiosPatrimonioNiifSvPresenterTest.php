<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\CambiosPatrimonioNiifSvPresenter;
use PHPUnit\Framework\TestCase;

class CambiosPatrimonioNiifSvPresenterTest extends TestCase
{
    public function test_calcular_reserva_legal_standard(): void
    {
        $monto = CambiosPatrimonioNiifSvPresenter::calcularReservaLegal(15_000.0, 10_950.0, 100_000.0);

        $this->assertEqualsWithDelta(1_050.0, $monto, 0.01);
    }

    public function test_calcular_reserva_legal_respects_cap(): void
    {
        $capital = 100_000.0;
        $tope = $capital * 0.20;
        $reservaAcum = $tope - 500.0;
        $monto = CambiosPatrimonioNiifSvPresenter::calcularReservaLegal(20_000.0, $reservaAcum, $capital);

        $this->assertEqualsWithDelta(500.0, $monto, 0.01);
    }

    public function test_calcular_reserva_legal_zero_on_loss(): void
    {
        $this->assertSame(0.0, CambiosPatrimonioNiifSvPresenter::calcularReservaLegal(-5_000.0, 0.0, 100_000.0));
    }

    public function test_calcular_reserva_legal_zero_when_cap_reached(): void
    {
        $this->assertSame(0.0, CambiosPatrimonioNiifSvPresenter::calcularReservaLegal(10_000.0, 20_000.0, 100_000.0));
    }

    public function test_validar_tope_reserva_legal(): void
    {
        $ok = CambiosPatrimonioNiifSvPresenter::validarTopeReservaLegal(15_000.0, 100_000.0);
        $bad = CambiosPatrimonioNiifSvPresenter::validarTopeReservaLegal(25_000.0, 100_000.0);

        $this->assertTrue($ok['ok']);
        $this->assertFalse($bad['ok']);
    }

    public function test_validar_dividendos(): void
    {
        $ok = CambiosPatrimonioNiifSvPresenter::validarDividendos(20_000.0, 10_000.0);
        $bad = CambiosPatrimonioNiifSvPresenter::validarDividendos(5_000.0, 10_000.0);

        $this->assertTrue($ok['ok']);
        $this->assertFalse($bad['ok']);
    }

    public function test_reserva_legal_movement_net_zero_in_total(): void
    {
        $reserva = CambiosPatrimonioNiifSvPresenter::calcularReservaLegal(22_000.0, 12_000.0, 100_000.0);
        $vals = [
            'capital_social' => 0.0,
            'reserva_legal' => $reserva,
            'utilidades_retenidas' => -$reserva,
            'utilidad_ejercicio' => 0.0,
            'superavit_revaluacion' => 0.0,
            'otras_reservas' => 0.0,
        ];

        $this->assertEqualsWithDelta(0.0, array_sum($vals), 0.01);
    }
}
