<?php

namespace Tests\Unit\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use App\Services\Comisiones\Calculators\PorVolumenCalculator;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;

class PorVolumenCalculatorTest extends TestCase
{
    private function ctx(float $ventas, array $tramos): object
    {
        return (object) [
            'id_empresa' => 1,
            'id_vendedor' => 5,
            'ventas' => $ventas,
            'regla' => (object) [
                'id' => 7,
                'tipo_calculo' => ComisionRegla::TIPO_POR_VOLUMEN,
                'config' => ['tramos' => $tramos],
            ],
        ];
    }

    public function test_evento_no_calcula(): void
    {
        $calc = new PorVolumenCalculator();
        $this->assertNull($calc->calcularEnEvento($this->ctx(10_000, [
            ['umbral' => 0, 'porcentaje' => 2],
        ])));
    }

    public function test_cierre_usa_ultimo_tramo_que_cumple(): void
    {
        $calc = new PorVolumenCalculator();
        $out = $calc->calcularEnCierre($this->ctx(1_500, [
            ['umbral' => 5_000, 'porcentaje' => 3],
            ['umbral' => 0, 'porcentaje' => 1],
            ['umbral' => 1_000, 'porcentaje' => 2],
        ]));

        $this->assertCount(1, $out);
        $this->assertSame(1_500.0, $out[0]->montoBase);
        $this->assertSame(2.0, $out[0]->porcentaje);
        $this->assertSame(30.0, $out[0]->montoComision);
        $this->assertSame(ComisionMovimiento::ORIGEN_AJUSTE_PERIODO, $out[0]->origen);
    }

    public function test_cierre_umbral_exacto_cumple(): void
    {
        $calc = new PorVolumenCalculator();
        $out = $calc->calcularEnCierre($this->ctx(5_000, [
            ['umbral' => 0, 'porcentaje' => 1],
            ['umbral' => 5_000, 'porcentaje' => 4],
        ]));

        $this->assertCount(1, $out);
        $this->assertSame(4.0, $out[0]->porcentaje);
        $this->assertSame(200.0, $out[0]->montoComision);
    }

    public function test_cierre_sin_tramo_no_genera_resultado(): void
    {
        $calc = new PorVolumenCalculator();
        $this->assertSame([], $calc->calcularEnCierre($this->ctx(500, [
            ['umbral' => 1_000, 'porcentaje' => 2],
        ])));
        $this->assertSame([], $calc->calcularEnCierre($this->ctx(100, [])));
    }

    public function test_factory_por_volumen(): void
    {
        $factory = new ComisionCalculatorFactory(new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => null,
            fn (int $e, int $s, ?int $idRegla = null) => null
        ));
        $this->assertInstanceOf(
            PorVolumenCalculator::class,
            $factory->for(ComisionRegla::TIPO_POR_VOLUMEN)
        );
    }
}
