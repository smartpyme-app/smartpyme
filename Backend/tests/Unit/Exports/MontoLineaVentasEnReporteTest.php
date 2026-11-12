<?php

namespace Tests\Unit\Exports;

use App\Exports\Support\MontoLineaVentasEnReporte;
use PHPUnit\Framework\TestCase;

class MontoLineaVentasEnReporteTest extends TestCase
{
    public function test_sql_sum_detalle_venta_sin_y_con_iva(): void
    {
        $this->assertSame('SUM(dv.total)', MontoLineaVentasEnReporte::sqlSumDetalleVenta('dv', false));
        $this->assertSame(
            'SUM(dv.total + COALESCE(dv.iva, 0))',
            MontoLineaVentasEnReporte::sqlSumDetalleVenta('dv', true)
        );
    }
}
