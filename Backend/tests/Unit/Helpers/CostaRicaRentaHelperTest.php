<?php

namespace Tests\Unit\Helpers;

use App\Helpers\CostaRicaRentaHelper;
use PHPUnit\Framework\TestCase;

class CostaRicaRentaHelperTest extends TestCase
{
    public function test_tramos_mensuales_2026(): void
    {
        $this->assertSame(0.0, CostaRicaRentaHelper::calcularImpuestoMensual(941000));
        $this->assertSame(44000.0, CostaRicaRentaHelper::calcularImpuestoMensual(1381000));
        $this->assertSame(200300.0, CostaRicaRentaHelper::calcularImpuestoMensual(2423000));
        $this->assertSame(684700.0, CostaRicaRentaHelper::calcularImpuestoMensual(4845000));
        $this->assertSame(684700.25, CostaRicaRentaHelper::calcularImpuestoMensual(4845001));
    }
}
