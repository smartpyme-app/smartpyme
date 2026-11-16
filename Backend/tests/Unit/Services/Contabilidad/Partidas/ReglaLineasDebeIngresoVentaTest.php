<?php

namespace Tests\Unit\Services\Contabilidad\Partidas;

use App\Services\Contabilidad\Partidas\ReglaLineasDebeIngresoVenta;
use Exception;
use PHPUnit\Framework\TestCase;

class ReglaLineasDebeIngresoVentaTest extends TestCase
{
    public function test_venta_simple_una_linea_debe(): void
    {
        $venta = (object) [
            'forma_pago' => 'Efectivo',
            'total' => 150.50,
        ];

        $lineas = ReglaLineasDebeIngresoVenta::lineasDebe($venta);

        $this->assertSame([['nombre' => 'Efectivo', 'monto' => 150.5]], $lineas);
    }

    public function test_pago_multiple_reparte_por_metodos(): void
    {
        $venta = (object) [
            'forma_pago' => 'Multiple',
            'total' => 100,
            'metodos_de_pago' => collect([
                (object) ['nombre' => 'Efectivo', 'total' => 40],
                (object) ['nombre' => 'Tarjeta', 'total' => 60],
            ]),
        ];

        $lineas = ReglaLineasDebeIngresoVenta::lineasDebe($venta);

        $this->assertCount(2, $lineas);
        $this->assertSame(100.0, array_sum(array_column($lineas, 'monto')));
    }

    public function test_pago_multiple_sin_metodos_falla(): void
    {
        $venta = (object) [
            'forma_pago' => 'Multiple',
            'total' => 50,
            'metodos_de_pago' => collect(),
        ];

        $this->expectException(Exception::class);
        ReglaLineasDebeIngresoVenta::lineasDebe($venta);
    }

    public function test_nombres_desde_ingresos_excluye_multiple_como_forma(): void
    {
        $ingresos = collect([
            (object) [
                'tipo' => 'venta',
                'forma_pago' => 'Multiple',
                'metodos_de_pago' => collect([
                    (object) ['nombre' => 'POS', 'total' => 30],
                    (object) ['nombre' => 'Efectivo', 'total' => 20],
                ]),
            ],
            (object) ['tipo' => 'abono', 'forma_pago' => 'Transferencia'],
        ]);

        $nombres = ReglaLineasDebeIngresoVenta::nombresFormasPagoDesdeIngresos($ingresos);

        sort($nombres);
        $this->assertSame(['Efectivo', 'POS', 'Transferencia'], $nombres);
    }
}
