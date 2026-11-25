<?php

namespace Tests\Unit\Services\Dte;

use App\Services\Dte\DteToIvaService;
use PHPUnit\Framework\TestCase;

/**
 * Costa Rica ATV → gasto: el JSON MH-compat no trae ventaTotal; las líneas
 * deben armarse con ventaGravada/exenta/nosuj (o cantidad×precio − descuento).
 */
class DteToIvaDetalleEgresoMontosTest extends TestCase
{
    public function test_linea_cr_sin_venta_total_usa_venta_gravada_y_calcula_iva(): void
    {
        $montos = DteToIvaService::montosDetalleEgresoDesdeItem([
            'cantidad' => 1,
            'precioUni' => 100000,
            'ventaGravada' => 100000,
            'ventaExenta' => 0,
            'ventaNoSuj' => 0,
            'montoDescu' => 0,
        ], 0.13);

        $this->assertSame(1.0, $montos['cantidad']);
        $this->assertSame(100000.0, $montos['precio_unitario']);
        $this->assertSame(100000.0, $montos['sub_total']);
        $this->assertSame(13000.0, $montos['iva']);
        $this->assertSame(113000.0, $montos['total']);
        $this->assertSame('gravada', $montos['tipo_gravado']);
        $this->assertTrue($montos['aplica_iva']);
    }

    public function test_linea_cr_con_descuento_usa_base_neta(): void
    {
        $montos = DteToIvaService::montosDetalleEgresoDesdeItem([
            'cantidad' => 21,
            'precioUni' => 2632.75,
            'ventaGravada' => 27643.88,
            'montoDescu' => 27643.87,
        ], 0.13);

        $this->assertSame(27643.88, $montos['sub_total']);
        $this->assertSame(3593.7, $montos['iva']);
        $this->assertSame(31237.58, $montos['total']);
    }

    public function test_linea_con_venta_total_e_iva_explicito_conserva_total(): void
    {
        $montos = DteToIvaService::montosDetalleEgresoDesdeItem([
            'cantidad' => 1,
            'precioUni' => 100,
            'ventaTotal' => 115,
            'iva' => 15,
        ], 0.13);

        $this->assertSame(100.0, $montos['sub_total']);
        $this->assertSame(15.0, $montos['iva']);
        $this->assertSame(115.0, $montos['total']);
    }
}
