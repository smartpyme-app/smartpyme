<?php

namespace Tests\Unit\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PedidoRestauranteControllerPrepararFacturaTest extends TestCase
{
    public function test_preparar_factura_trata_precios_del_pedido_como_sin_iva(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'prepararFactura');

        $this->assertNotFalse(
            strpos($source, '$precioSinIva = (float) $d->precio'),
            'Debe tomar el precio del pedido como base sin IVA'
        );
        $this->assertNotFalse(
            strpos($source, '$precioConIva = $pct > 0 ? round($precioSinIva * $factor, 4)'),
            'Debe calcular precio con IVA multiplicando la base'
        );
        $this->assertFalse(
            (bool) preg_match('/\$precioSinIva\s*=\s*\$pct\s*>\s*0\s*\?\s*round\(\$precioConIva\s*\/\s*\$factor/', $source),
            'No debe desglosar IVA dividiendo; el pedido ya guarda precio sin IVA'
        );
    }

    public function test_preparar_factura_emite_modo_de_descuento(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'prepararFactura');

        $this->assertNotFalse(
            strpos($source, "'descuento_porcentaje'"),
            'Debe enviar el porcentaje para que facturación conserve el modo %'
        );
        $this->assertNotFalse(
            strpos($source, "'descuento_is_monto'"),
            'Debe enviar si el descuento es monto para que facturación conserve el modo $'
        );
    }

    public function test_crear_detalle_guarda_descuento_porcentaje(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'crearDetalle');

        $this->assertNotFalse(
            strpos($source, 'descuento_porcentaje'),
            'Debe persistir descuento_porcentaje junto al monto'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $file = file($ref->getFileName());
        $start = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;

        return implode('', array_slice($file, $start, $length));
    }
}
