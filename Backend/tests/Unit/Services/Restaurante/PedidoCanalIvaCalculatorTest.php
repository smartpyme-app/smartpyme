<?php

namespace Tests\Unit\Services\Restaurante;

use App\Services\Restaurante\PedidoCanalIvaCalculator;
use PHPUnit\Framework\TestCase;

class PedidoCanalIvaCalculatorTest extends TestCase
{
    public function test_total_con_iva_usa_porcentaje_de_producto_sobre_la_base_sin_iva(): void
    {
        $detalles = [
            (object) [
                'cantidad' => 2,
                'precio' => 10.0,
                'descuento' => 0.0,
                'total' => 20.0,
                'producto' => (object) ['porcentaje_impuesto' => 13],
            ],
        ];

        $calc = PedidoCanalIvaCalculator::calcular($detalles, 13.0);

        $this->assertEquals(11.3, $calc['lineas'][0]['precio_con_iva']);
        $this->assertEquals(22.6, $calc['lineas'][0]['total_con_iva']);
        $this->assertEquals(2.6, $calc['iva']);
        $this->assertEquals(22.6, $calc['total_con_iva']);
        $this->assertEquals(20.0, $calc['subtotal']);
    }

    public function test_sin_iva_empresa_ni_producto_deja_totales_iguales_a_la_base(): void
    {
        $detalles = [
            (object) [
                'cantidad' => 1,
                'precio' => 50.0,
                'descuento' => 0.0,
                'total' => 50.0,
                'producto' => (object) ['porcentaje_impuesto' => null],
            ],
        ];

        $calc = PedidoCanalIvaCalculator::calcular($detalles, 0.0);

        $this->assertEquals(50.0, $calc['lineas'][0]['precio_con_iva']);
        $this->assertEquals(0.0, $calc['iva']);
        $this->assertEquals(50.0, $calc['total_con_iva']);
    }

    public function test_linea_exenta_no_usa_iva_de_empresa(): void
    {
        $detalles = [
            (object) [
                'cantidad' => 1,
                'precio' => 100.0,
                'descuento' => 0.0,
                'total' => 100.0,
                'producto' => (object) ['porcentaje_impuesto' => 0],
            ],
        ];

        $calc = PedidoCanalIvaCalculator::calcular($detalles, 13.0);

        $this->assertEquals(100.0, $calc['total_con_iva']);
        $this->assertEquals(0.0, $calc['iva']);
    }

    public function test_descuento_tambien_se_grava(): void
    {
        $detalles = [
            (object) [
                'cantidad' => 1,
                'precio' => 100.0,
                'descuento' => 10.0,
                'total' => 90.0,
                'producto' => (object) ['porcentaje_impuesto' => 13],
            ],
        ];

        $calc = PedidoCanalIvaCalculator::calcular($detalles, 13.0);

        $this->assertEquals(10.0, $calc['descuento']);
        $this->assertEquals(11.7, $calc['iva']);
        $this->assertEquals(101.7, $calc['total_con_iva']);
    }
}
