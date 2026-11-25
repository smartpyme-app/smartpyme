<?php

namespace Tests\Unit\Ventas;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Support\Ventas\VentaPdfLineaDescripcionServicio;
use Tests\TestCase;

class VentaPdfLineaDescripcionServicioTest extends TestCase
{
    public function test_devuelve_descripcion_catalogo_en_servicio(): void
    {
        $venta = new Venta(['id' => 1]);
        $producto = new Producto(['tipo' => 'Servicio', 'descripcion' => 'Alcance del servicio mensual']);
        $detalle = new Detalle(['id' => 10, 'descripcion' => 'Consultoría', 'id_producto' => 5]);
        $detalle->setRelation('producto', $producto);
        $venta->setRelation('detalles', collect([$detalle]));

        $empresa = new Empresa;

        self::assertSame(
            'Alcance del servicio mensual',
            VentaPdfLineaDescripcionServicio::textoParaPdf($venta, 1, $empresa)
        );
    }

    public function test_no_repite_si_descripcion_igual_a_nombre_linea(): void
    {
        $venta = new Venta(['id' => 1]);
        $producto = new Producto(['tipo' => 'Servicio', 'descripcion' => 'Mismo texto']);
        $detalle = new Detalle(['id' => 10, 'descripcion' => 'Mismo texto']);
        $detalle->setRelation('producto', $producto);
        $venta->setRelation('detalles', collect([$detalle]));

        self::assertSame('', VentaPdfLineaDescripcionServicio::textoParaPdf($venta, 1, new Empresa));
    }

    public function test_ignora_producto_no_servicio(): void
    {
        $venta = new Venta(['id' => 1]);
        $producto = new Producto(['tipo' => 'Producto', 'descripcion' => 'Extra']);
        $detalle = new Detalle(['id' => 10, 'descripcion' => 'Item']);
        $detalle->setRelation('producto', $producto);
        $venta->setRelation('detalles', collect([$detalle]));

        self::assertSame('', VentaPdfLineaDescripcionServicio::textoParaPdf($venta, 1, new Empresa));
    }
}