<?php

namespace Tests\Unit\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use App\Services\Comisiones\Calculators\PorMargenCalculator;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;

class PorMargenCalculatorTest extends TestCase
{
    private function ctx(array $over = []): object
    {
        $producto = (object) array_merge([
            'costo_promedio' => 10.0,
            'costo' => 8.0,
        ], $over['producto'] ?? []);

        $detalle = (object) array_merge([
            'id' => 1,
            'cantidad' => 2.0,
            'producto' => $producto,
        ], $over['detalle'] ?? []);

        $config = $over['config'] ?? ['porcentaje' => 10.0];
        $regla = (object) [
            'id' => 9,
            'tipo_calculo' => ComisionRegla::TIPO_POR_MARGEN,
            'config' => $config,
        ];

        return (object) [
            'id_empresa' => 1,
            'regla' => $regla,
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => $over['base'] ?? 100.0,
            'detalle' => $detalle,
        ];
    }

    public function test_usa_costo_promedio_cuando_es_positivo(): void
    {
        $calc = new PorMargenCalculator();
        $r = $calc->calcularEnEvento($this->ctx());

        $this->assertNotNull($r);
        $this->assertSame(80.0, $r->montoBase);
        $this->assertSame(10.0, $r->porcentaje);
        $this->assertSame(8.0, $r->montoComision);
        $this->assertSame([], $calc->calcularEnCierre($this->ctx()));
    }

    public function test_cae_a_costo_si_promedio_es_cero(): void
    {
        $calc = new PorMargenCalculator();
        $r = $calc->calcularEnEvento($this->ctx([
            'producto' => ['costo_promedio' => 0.0, 'costo' => 15.0],
        ]));

        $this->assertNotNull($r);
        $this->assertSame(70.0, $r->montoBase);
        $this->assertSame(7.0, $r->montoComision);
    }

    public function test_margen_negativo_no_genera_resultado(): void
    {
        $calc = new PorMargenCalculator();
        $r = $calc->calcularEnEvento($this->ctx([
            'base' => 10.0,
            'producto' => ['costo_promedio' => 20.0, 'costo' => 1.0],
        ]));

        $this->assertNull($r);
    }

    public function test_cero_porcentaje_no_genera_resultado(): void
    {
        $calc = new PorMargenCalculator();
        $this->assertNull($calc->calcularEnEvento($this->ctx([
            'config' => ['porcentaje' => 0],
        ])));
    }

    public function test_factory_por_margen(): void
    {
        $factory = new ComisionCalculatorFactory(new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => null,
            fn (int $e, int $s, ?int $idRegla = null) => null
        ));
        $this->assertInstanceOf(
            PorMargenCalculator::class,
            $factory->for(ComisionRegla::TIPO_POR_MARGEN)
        );
    }
}
