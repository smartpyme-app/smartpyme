<?php

namespace Tests\Unit\Support\Inventario;

use App\Support\Inventario\RecalcularPreciosTipoCambio;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RecalcularPreciosTipoCambioTest extends TestCase
{
    public function test_precio_100_catalogo_25_nuevo_26_queda_104(): void
    {
        $factor = RecalcularPreciosTipoCambio::factor(26.0, 25.0);
        $this->assertEqualsWithDelta(1.04, $factor, 0.0000001);
        $this->assertSame(104.00, RecalcularPreciosTipoCambio::escalar(100.0, $factor));
    }

    public function test_escala_precio_y_listas_extra_no_costo(): void
    {
        $out = RecalcularPreciosTipoCambio::aplicarAProducto([
            'precio' => 100,
            'precio_sin_iva' => 86.21,
            'precio_con_iva' => 100,
            'costo' => 40,
            'precios' => [
                ['id' => 1, 'precio' => 90],
            ],
        ], RecalcularPreciosTipoCambio::factor(26.0, 25.0));

        $this->assertSame(104.00, $out['precio']);
        $this->assertSame(89.66, $out['precio_sin_iva']);
        $this->assertSame(104.00, $out['precio_con_iva']);
        $this->assertSame(40, $out['costo']);
        $this->assertSame(93.60, $out['precios'][0]['precio']);
    }

    public function test_tipos_productos_incluye_compuesto_no_servicio(): void
    {
        $this->assertSame(
            ['Producto', 'Compuesto'],
            RecalcularPreciosTipoCambio::tipos(true, false)
        );
        $this->assertSame(['Servicio'], RecalcularPreciosTipoCambio::tipos(false, true));
    }

    public function test_sin_checkboxes_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RecalcularPreciosTipoCambio::tipos(false, false);
    }

    public function test_tc_invalido_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RecalcularPreciosTipoCambio::factor(26.0, 0.0);
    }

    public function test_primera_vez_copia_venta_a_catalogo_sin_factor(): void
    {
        $r = RecalcularPreciosTipoCambio::sembrarCatalogoSiFalta(null, 25.5);
        $this->assertTrue($r['sembrar']);
        $this->assertSame(25.5, $r['catalogo']);
        $this->assertSame(25.5, $r['venta']);
    }

    public function test_guardar_venta_no_pisa_catalogo(): void
    {
        $r = RecalcularPreciosTipoCambio::sembrarCatalogoSiFalta(25.0, 26.0);
        $this->assertFalse($r['sembrar']);
        $this->assertSame(25.0, $r['catalogo']);
        $this->assertSame(26.0, $r['venta']);
    }

    public function test_sugerido_usa_venta_empresa_si_existe(): void
    {
        $this->assertSame(26.0, RecalcularPreciosTipoCambio::sugeridoVenta(26.0, 24.8));
        $this->assertSame(24.8, RecalcularPreciosTipoCambio::sugeridoVenta(null, 24.8));
    }
}
