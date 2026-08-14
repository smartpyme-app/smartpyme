<?php

namespace Tests\Unit\Services\Comisiones\Calculators;

use App\Models\Comisiones\ComisionRegla;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use App\Services\Comisiones\Calculators\PorCategoriaCalculator;
use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

class PorCategoriaCalculatorTest extends TestCase
{
    public function test_usa_porcentaje_del_resolver(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => 2.0,
            fn (int $e, int $s, ?int $idRegla = null) => null
        );
        $calc = new PorCategoriaCalculator($resolver);
        $detalle = (object) ['id' => 1];
        $regla = (object) ['id' => 9, 'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA];

        $ctx = (object) [
            'id_empresa' => 1,
            'regla' => $regla,
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => 100.0,
            'detalle' => $detalle,
        ];

        $r = $calc->calcularEnEvento($ctx);
        $this->assertNotNull($r);
        $this->assertSame(100.0, $r->montoBase);
        $this->assertSame(2.0, $r->porcentaje);
        $this->assertSame(2.0, $r->montoComision);
        $this->assertSame(10, $r->idCategoria);
        $this->assertSame([], $calc->calcularEnCierre($ctx));
    }

    public function test_cero_no_genera_resultado(): void
    {
        $calc = new PorCategoriaCalculator(new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => 0.0,
            fn (int $e, int $s, ?int $idRegla = null) => null
        ));
        $ctx = (object) [
            'id_empresa' => 1,
            'regla' => (object) ['id' => 1],
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => 100.0,
            'detalle' => new stdClass(),
        ];
        $this->assertNull($calc->calcularEnEvento($ctx));
    }

    public function test_factory_por_categoria(): void
    {
        $factory = new ComisionCalculatorFactory(new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => null,
            fn (int $e, int $s, ?int $idRegla = null) => null
        ));
        $this->assertInstanceOf(
            PorCategoriaCalculator::class,
            $factory->for(ComisionRegla::TIPO_POR_CATEGORIA)
        );
    }

    public function test_pasa_id_regla_al_resolver_cuando_hay_regla(): void
    {
        $seen = null;
        $resolver = new ComisionPorcentajeResolver(
            function (int $e, int $c, ?int $idRegla = null) use (&$seen) {
                $seen = $idRegla;

                return 2.0;
            },
            fn (int $e, int $s, ?int $idRegla = null) => null
        );
        $calc = new PorCategoriaCalculator($resolver);
        $r = $calc->calcularEnEvento((object) [
            'id_empresa' => 1,
            'regla' => (object) ['id' => 9, 'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA],
            'id_categoria' => 10,
            'id_subcategoria' => null,
            'base' => 100.0,
            'detalle' => new stdClass(),
        ]);
        $this->assertNotNull($r);
        $this->assertSame(9, $seen);
    }
}
