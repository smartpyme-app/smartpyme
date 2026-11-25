<?php

namespace Tests\Unit\MH;

use PHPUnit\Framework\TestCase;

/** descuento_puntos bruto → descuGravada neto; totalPagar sigue siendo el de BD. */
class CcfDescuentoPuntosResumenTest extends TestCase
{
    public function test_descuento_puntos_solo_mapea_descu_gravada_neto(): void
    {
        $totalConIva = 40.0;
        $descuentoPuntos = 6.05;
        $totalPagarBd = 33.95; // 40 - 6.05
        $ivaBd = 3.90;
        $totalGravada = 35.40;

        $descuGravada = floatval(number_format($descuentoPuntos / 1.13, 2, '.', ''));
        $subTotal = floatval(number_format($totalGravada - $descuGravada, 2, '.', ''));
        $montoTotalOperacion = floatval(number_format($subTotal + $ivaBd, 2, '.', ''));

        $this->assertSame(5.35, $descuGravada);
        $this->assertSame(30.05, $subTotal);
        $this->assertSame(33.95, $montoTotalOperacion);
        $this->assertSame($totalPagarBd, round($totalConIva - $descuentoPuntos, 2));
        $this->assertSame($totalPagarBd, $montoTotalOperacion);
    }
}
