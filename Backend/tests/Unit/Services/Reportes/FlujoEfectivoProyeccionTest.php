<?php

namespace Tests\Unit\Services\Reportes;

use App\Services\ReportesService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class FlujoEfectivoProyeccionTest extends TestCase
{
    public function test_frecuencia_anual_gana_aunque_la_suscripcion_diga_mensual(): void
    {
        $this->assertSame('anual', ReportesService::cadenciaCobro('Anual', 'Mensual', 'Mensual'));
        $this->assertSame('anual', ReportesService::cadenciaCobro('Anual', 'Anual', 'Mensual'));
    }

    public function test_sin_frecuencia_valida_usa_el_siguiente_campo(): void
    {
        $this->assertSame('anual', ReportesService::cadenciaCobro('', 'Anual', 'Mensual'));
        $this->assertSame('trimestral', ReportesService::cadenciaCobro(null, 'Pro', 'Trimestral'));
        $this->assertSame('sin_frecuencia', ReportesService::cadenciaCobro('Pro', 'Estándar', ''));
        $this->assertSame('sin_frecuencia', ReportesService::cadenciaCobro(null, null, null));
        $this->assertSame('Sin frecuencia', ReportesService::etiquetaCadencia('sin_frecuencia'));
    }

    public function test_sin_frecuencia_cobra_solo_la_fecha_conocida(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-10-29'),
            'sin_frecuencia'
        );

        $this->assertSame(['2026-10-29'], $fechas);
    }

    public function test_sin_frecuencia_no_inventa_cobros_si_la_fecha_ya_paso(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-09-07'),
            'sin_frecuencia'
        );

        $this->assertSame([], $fechas);
    }

    public function test_anual_cobra_una_vez_en_la_fecha_de_proximo_pago(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-11-07'),
            'anual'
        );

        $this->assertSame(['2026-11-07'], $fechas);
    }

    public function test_anual_de_octubre_no_se_repite_los_meses_siguientes(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-10-29'),
            'anual'
        );

        $this->assertSame(['2026-10-29'], $fechas);
    }

    public function test_mensual_repite_el_mismo_dia_cada_mes(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-10-29'),
            'mensual'
        );

        $this->assertSame([
            '2026-10-29',
            '2026-11-29',
            '2026-12-29',
            '2027-01-29',
            '2027-02-28',
            '2027-03-29',
        ], $fechas);
    }

    public function test_anual_en_un_rango_largo_incluye_el_aniversario(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-12-31')->endOfDay(),
            Carbon::parse('2026-11-07'),
            'anual'
        );

        $this->assertSame(['2026-11-07', '2027-11-07'], $fechas);
    }

    public function test_trimestral_avanza_tres_meses(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-10-01'),
            Carbon::parse('2027-03-31')->endOfDay(),
            Carbon::parse('2026-10-29'),
            'trimestral'
        );

        $this->assertSame(['2026-10-29', '2027-01-29'], $fechas);
    }

    public function test_dia_31_no_se_desborda_en_febrero(): void
    {
        $fechas = ReportesService::fechasCobro(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-31')->endOfDay(),
            Carbon::parse('2026-01-31'),
            'mensual'
        );

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $fechas);
    }
}
