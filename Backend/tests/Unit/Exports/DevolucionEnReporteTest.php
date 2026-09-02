<?php

namespace Tests\Unit\Exports;

use App\Exports\Support\DevolucionEnReporte;
use PHPUnit\Framework\TestCase;

class DevolucionEnReporteTest extends TestCase
{
    public function test_niega_montos_positivos_y_deja_cero(): void
    {
        $this->assertSame(-100.5, DevolucionEnReporte::negar(100.5));
        $this->assertSame(0.0, DevolucionEnReporte::negar(0));
        $this->assertSame(-25.0, DevolucionEnReporte::negar(-25));
    }

    public function test_montos_de_venta_quedan_negativos_para_que_el_total_del_periodo_sea_neto(): void
    {
        $devolucion = (object) [
            'sub_total' => 100.0,
            'descuento' => 10.0,
            'iva' => 11.7,
            'total' => 101.7,
            'total_costo' => 40.0,
            'cuenta_a_terceros' => 5.0,
            'propina' => 2.0,
        ];

        $montos = DevolucionEnReporte::montosVentaNegados($devolucion);

        $this->assertSame(-40.0, $montos['costo']);
        $this->assertSame(-5.0, $montos['cuenta_terceros']);
        $this->assertSame(-100.0, $montos['sub_total']);
        $this->assertSame(-10.0, $montos['descuento']);
        $this->assertSame(-11.7, $montos['iva']);
        $this->assertSame(-50.0, $montos['utilidad']);
        $this->assertSame(-90.0, $montos['total_sin_iva']);
        $this->assertSame(-101.7, $montos['total']);
        $this->assertSame(-2.0, $montos['propina']);
    }

    public function test_detalle_niega_cantidad_y_totales_pero_no_el_costo_unitario(): void
    {
        $detalle = (object) [
            'cantidad' => 2.0,
            'costo' => 10.0,
            'precio' => 20.0,
            'descuento' => 1.0,
            'total' => 39.0,
        ];

        $montos = DevolucionEnReporte::montosDetalleNegados($detalle, 5.07);

        $this->assertSame(-2.0, $montos['cantidad']);
        $this->assertSame(10.0, $montos['costo']);
        $this->assertSame(20.0, $montos['precio']);
        $this->assertSame(-1.0, $montos['descuento']);
        $this->assertSame(-5.07, $montos['iva']);
        $this->assertSame(-19.0, $montos['utilidad']);
        $this->assertSame(-44.07, $montos['total']);
    }

    public function test_marca_y_detecta_fila_de_devolucion(): void
    {
        $row = (object) ['id' => 1];
        $this->assertFalse(DevolucionEnReporte::esDevolucion($row));

        DevolucionEnReporte::marcar($row);

        $this->assertTrue(DevolucionEnReporte::esDevolucion($row));
        $this->assertSame('devolucion', $row->origen_export);
        $this->assertSame('Devolución', DevolucionEnReporte::ESTADO);
    }

    public function test_venta_bruta_menos_devolucion_del_periodo_da_neto(): void
    {
        $ventaBruta = 250.0;
        $devolucion = (object) ['total' => 40.0, 'sub_total' => 0, 'descuento' => 0, 'iva' => 0, 'total_costo' => 0];

        $neto = $ventaBruta + DevolucionEnReporte::montosVentaNegados($devolucion)['total'];

        $this->assertSame(210.0, $neto);
    }
}
