<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\PlanCuotasIguales;
use PHPUnit\Framework\TestCase;

class PlanCuotasIgualesTest extends TestCase
{
    public function test_reparte_centavos_en_la_ultima_cuota(): void
    {
        $cuotas = PlanCuotasIguales::generar(100.00, 3, '2026-01-15');

        $this->assertCount(3, $cuotas);
        $this->assertSame(33.33, $cuotas[0]['monto']);
        $this->assertSame(33.33, $cuotas[1]['monto']);
        $this->assertSame(33.34, $cuotas[2]['monto']);
        $this->assertEqualsWithDelta(100.00, array_sum(array_column($cuotas, 'monto')), 0.001);
    }

    public function test_fechas_mensuales_desde_inicio(): void
    {
        $cuotas = PlanCuotasIguales::generar(90.00, 3, '2026-01-15');

        $this->assertSame('2026-01-15', $cuotas[0]['fecha_vencimiento']);
        $this->assertSame('2026-02-15', $cuotas[1]['fecha_vencimiento']);
        $this->assertSame('2026-03-15', $cuotas[2]['fecha_vencimiento']);
        $this->assertSame([1, 2, 3], array_column($cuotas, 'numero'));
    }

    public function test_rechaza_n_menor_que_2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanCuotasIguales::generar(100.00, 1, '2026-01-15');
    }
}
