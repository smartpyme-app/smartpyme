<?php

namespace Tests\Unit;

use App\Models\Indicador;
use PHPUnit\Framework\TestCase;

class IndicadorDevolucionesDashTest extends TestCase
{
    public function test_resta_la_devolucion_por_el_canal_de_la_venta(): void
    {
        $devolucion = (object) [
            'total' => 25.5,
            'venta' => (object) ['id_canal' => 3, 'forma_pago' => 'Efectivo'],
        ];

        $porCanal = Indicador::filtrarDevolucionesPorVenta([$devolucion], 'id_canal', 3);
        $otroCanal = Indicador::filtrarDevolucionesPorVenta([$devolucion], 'id_canal', 9);

        $this->assertCount(1, $porCanal);
        $this->assertSame(25.5, $porCanal->sum('total'));
        $this->assertCount(0, $otroCanal);
    }

    public function test_metodo_de_pago_resta_la_devolucion_en_el_mismo_reparto(): void
    {
        $mixta = (object) ['id' => 1, 'estado' => 'Pagada', 'forma_pago' => 'Multiple', 'total' => 100];
        $transferencia = (object) ['id' => 2, 'estado' => 'Pagada', 'forma_pago' => 'Transferencia', 'total' => 40];
        $metodos = collect([
            1 => collect([
                (object) ['nombre' => 'N1co', 'total' => 60],
                (object) ['nombre' => 'Tarjeta de crédito/débito', 'total' => 40],
            ]),
        ]);
        $devoluciones = collect([
            (object) ['id_venta' => 1, 'total' => 10, 'venta' => $mixta],
            (object) [
                'id_venta' => 99,
                'total' => 5,
                'venta' => (object) ['id' => 99, 'forma_pago' => 'Transferencia', 'total' => 80],
            ],
        ]);

        $filas = collect(Indicador::acumularFormasPago(
            collect([$mixta, $transferencia]),
            $devoluciones,
            $metodos
        ))->keyBy('nombre');

        $this->assertEquals(54.0, $filas['N1co']['total']);
        $this->assertEquals(36.0, $filas['Tarjeta de crédito/débito']['total']);
        $this->assertEquals(35.0, $filas['Transferencia']['total']);
        $this->assertEquals(125.0, $filas->sum('total'));
    }
}
