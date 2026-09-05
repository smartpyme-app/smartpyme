<?php

namespace Tests\Unit;

use App\Services\Admin\ResumenCajaCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class IndicadorResumenCajaTest extends TestCase
{
    public function test_venta_wompi_pagada_con_abono_del_mismo_dia_no_duplica_el_ingreso(): void
    {
        $totales = ResumenCajaCalculator::totalesPorForma(
            'Wompi',
            Collection::make([
                (object) ['id' => 10, 'forma_pago' => 'Wompi', 'total' => 50.00],
            ]),
            Collection::make([
                (object) ['id' => 1, 'id_venta' => 10, 'forma_pago' => 'Wompi', 'total' => 50.00],
            ]),
            Collection::make(),
            Collection::make()
        );

        $this->assertSame(1, $totales['cantidad']);
        $this->assertEquals(50.00, $totales['total']);
    }

    public function test_venta_pagada_sin_abono_se_cuenta(): void
    {
        $totales = ResumenCajaCalculator::totalesPorForma(
            'Efectivo',
            Collection::make([
                (object) ['id' => 11, 'forma_pago' => 'Efectivo', 'total' => 20.00],
            ]),
            Collection::make(),
            Collection::make(),
            Collection::make()
        );

        $this->assertSame(1, $totales['cantidad']);
        $this->assertEquals(20.00, $totales['total']);
    }

    public function test_abono_de_credito_previo_se_cuenta(): void
    {
        $totales = ResumenCajaCalculator::totalesPorForma(
            'Wompi',
            Collection::make(),
            Collection::make([
                (object) ['id' => 2, 'id_venta' => 99, 'forma_pago' => 'Wompi', 'total' => 30.00],
            ]),
            Collection::make(),
            Collection::make()
        );

        $this->assertSame(1, $totales['cantidad']);
        $this->assertEquals(30.00, $totales['total']);
    }

    public function test_venta_pagada_y_abono_de_otra_venta_se_suman(): void
    {
        $totales = ResumenCajaCalculator::totalesPorForma(
            'Wompi',
            Collection::make([
                (object) ['id' => 12, 'forma_pago' => 'Wompi', 'total' => 40.00],
            ]),
            Collection::make([
                (object) ['id' => 3, 'id_venta' => 88, 'forma_pago' => 'Wompi', 'total' => 15.00],
            ]),
            Collection::make(),
            Collection::make()
        );

        $this->assertSame(2, $totales['cantidad']);
        $this->assertEquals(55.00, $totales['total']);
    }
}
