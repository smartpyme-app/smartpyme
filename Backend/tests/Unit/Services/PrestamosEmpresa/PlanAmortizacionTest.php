<?php

namespace Tests\Unit\Services\PrestamosEmpresa;

use App\Services\PrestamosEmpresa\PlanAmortizacion;
use PHPUnit\Framework\TestCase;

class PlanAmortizacionTest extends TestCase
{
    public function test_cuotas_iguales_absorbe_centavos_en_la_ultima(): void
    {
        $cuotas = PlanAmortizacion::iguales(100.00, 3, '2026-02-01', 'mensual');

        $this->assertCount(3, $cuotas);
        $this->assertSame(33.33, $cuotas[0]['capital']);
        $this->assertSame(0.0, $cuotas[0]['interes']);
        $this->assertSame(33.34, $cuotas[2]['capital']);
        $this->assertEqualsWithDelta(100.00, array_sum(array_column($cuotas, 'capital')), 0.001);
        $this->assertSame('2026-02-01', $cuotas[0]['fecha_vencimiento']);
        $this->assertSame('2026-04-01', $cuotas[2]['fecha_vencimiento']);
    }

    public function test_francesa_dos_cuotas_parte_interes_y_cierra_capital(): void
    {
        $cuotas = PlanAmortizacion::francesa(10000.00, 12.0, 2, '2026-02-01', 'mensual');

        $this->assertCount(2, $cuotas);
        $this->assertSame(100.00, $cuotas[0]['interes']);
        $this->assertSame(4975.12, $cuotas[0]['capital']);
        $this->assertSame(5075.12, $cuotas[0]['total']);
        $this->assertEqualsWithDelta(10000.00, $cuotas[0]['capital'] + $cuotas[1]['capital'], 0.001);
        $this->assertSame(0.0, round(10000.00 - $cuotas[0]['capital'] - $cuotas[1]['capital'], 2));
        $this->assertSame($cuotas[1]['capital'], 5024.88);
        $this->assertSame(50.25, $cuotas[1]['interes']);
    }

    public function test_fechas_quincenal_y_semanal(): void
    {
        $quincenal = PlanAmortizacion::iguales(90.00, 3, '2026-01-15', 'quincenal');
        $this->assertSame(['2026-01-15', '2026-01-29', '2026-02-12'], array_column($quincenal, 'fecha_vencimiento'));

        $semanal = PlanAmortizacion::iguales(90.00, 3, '2026-01-15', 'semanal');
        $this->assertSame(['2026-01-15', '2026-01-22', '2026-01-29'], array_column($semanal, 'fecha_vencimiento'));
    }

    public function test_rechaza_plazo_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanAmortizacion::iguales(100.00, 1, '2026-01-15', 'mensual');
    }

    public function test_monto_a_cobrar_no_supera_saldo(): void
    {
        $filas = [
            ['total' => 500.00],
            ['total' => 500.00],
        ];
        $this->assertSame(300.00, PlanAmortizacion::montoACobrar($filas, 300.00));
        $this->assertSame(1000.00, PlanAmortizacion::montoACobrar($filas, 1500.00));
    }

    public function test_clasifica_largo_plazo_si_supera_un_anio(): void
    {
        $this->assertSame('largo', PlanAmortizacion::clasificacion(13, 'mensual'));
        $this->assertSame('corto', PlanAmortizacion::clasificacion(12, 'mensual'));
        $this->assertSame('largo', PlanAmortizacion::clasificacion(27, 'quincenal'));
        $this->assertSame('largo', PlanAmortizacion::clasificacion(53, 'semanal'));
    }
}
