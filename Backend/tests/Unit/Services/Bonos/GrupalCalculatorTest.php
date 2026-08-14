<?php

namespace Tests\Unit\Services\Bonos;

use App\Services\Bonos\Calculators\BonoCalculatorFactory;
use App\Services\Bonos\Calculators\GrupalCalculator;
use PHPUnit\Framework\TestCase;

class GrupalCalculatorTest extends TestCase
{
    public function test_factory_resuelve_grupal(): void
    {
        $calc = (new BonoCalculatorFactory())->for('grupal');
        $this->assertInstanceOf(GrupalCalculator::class, $calc);
    }

    public function test_no_reparte_si_el_equipo_no_cumple_meta(): void
    {
        $out = (new GrupalCalculator())->repartir(
            ['meta' => 1000, 'bono' => 100, 'reparto' => 'equitativo'],
            [1 => 400.0, 2 => 400.0],
        );

        $this->assertSame([1 => 0.0, 2 => 0.0], $out);
    }

    public function test_reparto_equitativo_cuando_el_equipo_cumple(): void
    {
        $out = (new GrupalCalculator())->repartir(
            ['meta' => 1000, 'bono' => 100, 'reparto' => 'equitativo'],
            [1 => 600.0, 2 => 400.0],
        );

        $this->assertSame([1 => 50.0, 2 => 50.0], $out);
    }

    public function test_reparto_proporcional_segun_ventas(): void
    {
        $out = (new GrupalCalculator())->repartir(
            ['meta' => 1000, 'bono' => 100, 'reparto' => 'proporcional'],
            [1 => 600.0, 2 => 400.0],
        );

        $this->assertSame([1 => 60.0, 2 => 40.0], $out);
    }
}
