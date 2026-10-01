<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EstadoResultadosNiifSvPresenterTest extends TestCase
{
    public function test_rubro_ingresos_cuenta_sucursal_clasifica_ventas_brutas(): void
    {
        $L = $this->distribuirIngresoSample(
            'SUCURSAL SANTA ANA 510102 ingresos',
            'ingresos',
            44.40
        );

        $this->assertEqualsWithDelta(44.40, $L['ventas_brutas'], 0.01);
        $this->assertEqualsWithDelta(0.0, $L['otros_ingresos'], 0.01);
    }

    public function test_rubro_ingresos_intereses_sigue_en_otros_ing_intereses(): void
    {
        $L = $this->distribuirIngresoSample(
            'intereses ganados cuenta banco 510999 ingresos',
            'ingresos',
            100.0
        );

        $this->assertEqualsWithDelta(100.0, $L['otros_ing_intereses'], 0.01);
        $this->assertEqualsWithDelta(0.0, $L['ventas_brutas'], 0.01);
    }

    /**
     * @return array<string, float>
     */
    private function distribuirIngresoSample(string $hRaw, string $rubro, float $monto): array
    {
        $presenter = new EstadoResultadosNiifSvPresenter();
        $rc = new ReflectionClass($presenter);

        $empty = $rc->getMethod('emptyLineKeys');
        $empty->setAccessible(true);
        $Lprop = $rc->getProperty('L');
        $Lprop->setAccessible(true);
        $Lprop->setValue($presenter, $empty->invoke($presenter));

        $normalize = $rc->getMethod('normalize');
        $normalize->setAccessible(true);
        $h = $normalize->invoke($presenter, $hRaw);

        $distribuir = $rc->getMethod('distribuirIngreso');
        $distribuir->setAccessible(true);
        $distribuir->invoke($presenter, $h, $monto, 0.0, 0.0, $rubro);

        return $Lprop->getValue($presenter);
    }
}
