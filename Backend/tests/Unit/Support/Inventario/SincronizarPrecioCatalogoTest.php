<?php

namespace Tests\Unit\Support\Inventario;

use App\Support\Inventario\SincronizarPrecioCatalogo;
use PHPUnit\Framework\TestCase;

class SincronizarPrecioCatalogoTest extends TestCase
{
    public function test_sincroniza_precio_con_iva_desde_base(): void
    {
        $producto = (object) [
            'precio' => 13.274336,
            'precio_sin_iva' => 99,
            'precio_con_iva' => 99,
        ];

        SincronizarPrecioCatalogo::aplicar($producto, 13.0);

        $this->assertSame(13.274336, $producto->precio);
        $this->assertSame(13.274336, $producto->precio_sin_iva);
        $this->assertSame(15.0, $producto->precio_con_iva);
    }

    public function test_porcentaje_efectivo_usa_empresa_si_producto_en_cero(): void
    {
        $this->assertSame(13.0, SincronizarPrecioCatalogo::porcentajeIvaEfectivo(0, 13));
        $this->assertSame(15.0, SincronizarPrecioCatalogo::porcentajeIvaEfectivo(15, 13));
    }
}
