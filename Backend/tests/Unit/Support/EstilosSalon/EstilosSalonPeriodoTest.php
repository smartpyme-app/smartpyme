<?php

namespace Tests\Unit\Support\EstilosSalon;

use App\Support\EstilosSalon\EstilosSalonPeriodo;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class EstilosSalonPeriodoTest extends TestCase
{
    /**
     * @dataProvider diasDeEnvio
     */
    public function test_detecta_dias_de_envio_segun_largo_del_mes(string $fecha, bool $esperado): void
    {
        $this->assertSame($esperado, EstilosSalonPeriodo::esDiaEnvio(Carbon::parse($fecha)));
    }

    public static function diasDeEnvio(): array
    {
        return [
            'abril 7 (30 días)' => ['2026-04-07', true],
            'abril 8 no envía' => ['2026-04-08', false],
            'abril 15' => ['2026-04-15', true],
            'abril 22' => ['2026-04-22', true],
            'abril 30' => ['2026-04-30', true],
            'mayo 7 no envía (31 días)' => ['2026-05-07', false],
            'mayo 8' => ['2026-05-08', true],
            'mayo 15' => ['2026-05-15', true],
            'mayo 23' => ['2026-05-23', true],
            'mayo 31' => ['2026-05-31', true],
            'febrero 6' => ['2026-02-06', true],
            'febrero 7 no envía' => ['2026-02-07', false],
            'febrero 15' => ['2026-02-15', true],
            'febrero 21' => ['2026-02-21', true],
            'febrero 28' => ['2026-02-28', true],
            'febrero 29 bisiesto no envía' => ['2028-02-29', false],
            'febrero 28 bisiesto sí' => ['2028-02-28', true],
        ];
    }

    public function test_periodo_cron_es_nulo_si_no_es_dia_de_envio(): void
    {
        $this->assertNull(EstilosSalonPeriodo::periodoCron(Carbon::parse('2026-05-10')));
    }

    public function test_periodo_cron_acumula_desde_el_dia_1(): void
    {
        $this->assertSame(
            ['2026-05-01', '2026-05-23'],
            EstilosSalonPeriodo::periodoCron(Carbon::parse('2026-05-23'))
        );
    }

    public function test_rango_sugerido_es_del_1_a_hoy(): void
    {
        $this->assertSame(
            ['2026-08-01', '2026-08-19'],
            EstilosSalonPeriodo::rangoAcumulado(Carbon::parse('2026-08-19'))
        );
    }

    public function test_empresa_permitida_usa_la_lista_fija(): void
    {
        $this->assertTrue(EstilosSalonPeriodo::empresaPermitida(396));
        $this->assertFalse(EstilosSalonPeriodo::empresaPermitida(1));
    }
}
