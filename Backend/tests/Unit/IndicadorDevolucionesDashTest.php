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
}
