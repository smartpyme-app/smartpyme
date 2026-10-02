<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\RecurrenciaCalendario;
use PHPUnit\Framework\TestCase;

class RecurrenciaCalendarioTest extends TestCase
{
    public function test_mensual_cae_el_mismo_dia_del_mes_siguiente(): void
    {
        $this->assertTrue(RecurrenciaCalendario::corresponde('mensual', '2026-10-02', '2026-11-02'));
        $this->assertSame('2026-11', RecurrenciaCalendario::periodo('2026-11-02'));
    }

    public function test_no_repite_el_dia_de_la_plantilla_ni_otro_dia(): void
    {
        $this->assertFalse(RecurrenciaCalendario::corresponde('mensual', '2026-10-02', '2026-10-02'));
        $this->assertFalse(RecurrenciaCalendario::corresponde('mensual', '2026-10-02', '2026-11-03'));
    }

    public function test_un_dia_que_el_mes_no_tiene_no_corre(): void
    {
        $this->assertFalse(RecurrenciaCalendario::corresponde('mensual', '2026-01-31', '2026-02-28'));
        $this->assertTrue(RecurrenciaCalendario::corresponde('mensual', '2026-01-31', '2026-03-31'));
    }

    public function test_anual_solo_el_aniversario(): void
    {
        $this->assertTrue(RecurrenciaCalendario::corresponde('anual', '2026-10-02', '2027-10-02'));
        $this->assertFalse(RecurrenciaCalendario::corresponde('anual', '2026-10-02', '2027-11-02'));
        $this->assertSame('2027-10', RecurrenciaCalendario::periodo('2027-10-02'));
    }

    public function test_dia_generacion_distinto_a_la_fecha_de_la_plantilla(): void
    {
        $this->assertFalse(RecurrenciaCalendario::corresponde('mensual', '2026-10-10', '2026-11-10', 5));
        $this->assertTrue(RecurrenciaCalendario::corresponde('mensual', '2026-10-10', '2026-11-05', 5));
    }

    public function test_anual_usa_mes_de_plantilla_y_dia_configurado(): void
    {
        $this->assertTrue(RecurrenciaCalendario::corresponde('anual', '2026-10-10', '2027-10-05', 5));
        $this->assertFalse(RecurrenciaCalendario::corresponde('anual', '2026-10-10', '2027-11-05', 5));
    }
}
