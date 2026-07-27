<?php

namespace Tests\Unit\Services\Comisiones;

use App\Services\Comisiones\ComisionBaseCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ComisionBaseCalculatorTest extends TestCase
{
    public function test_subtotal_sin_iva_usa_gravada_exenta_menos_noop(): void
    {
        $detalle = (object) [
            'gravada' => 100.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
            'descuento' => 10.0,
            'total' => 113.0,
            'sub_total' => 100.0,
            'iva' => 13.0,
        ];
        // Convención v1 default: gravada+exenta+no_sujeta (ya net de descuento en líneas SV)
        // ponytail: gravada/exenta/no_sujeta en detalle SV ya vienen post-descuento; no restar descuento otra vez.
        $calc = new ComisionBaseCalculator();
        $this->assertEquals(100.0, $calc->calcular($detalle, 'subtotal_sin_iva'));
    }

    public function test_subtotal_sin_iva_suma_gravada_exenta_y_no_sujeta(): void
    {
        $detalle = (object) [
            'gravada' => 80.0,
            'exenta' => 15.0,
            'no_sujeta' => 5.0,
        ];
        $calc = new ComisionBaseCalculator();
        $this->assertEquals(100.0, $calc->calcular($detalle, 'subtotal_sin_iva'));
    }

    public function test_total_con_iva(): void
    {
        $detalle = (object) ['total' => 113.0, 'gravada' => 100.0, 'exenta' => 0, 'no_sujeta' => 0];
        $calc = new ComisionBaseCalculator();
        $this->assertEquals(113.0, $calc->calcular($detalle, 'total_con_iva'));
    }

    public function test_bruto_sin_descuento(): void
    {
        $detalle = (object) ['sub_total' => 110.0, 'gravada' => 100.0, 'descuento' => 10.0];
        $calc = new ComisionBaseCalculator();
        $this->assertEquals(110.0, $calc->calcular($detalle, 'bruto_sin_descuento'));
    }

    public function test_base_calculo_desconocida_lanza_excepcion(): void
    {
        $calc = new ComisionBaseCalculator();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base_calculo desconocida: invalida');
        $calc->calcular((object) ['gravada' => 1.0], 'invalida');
    }
}
