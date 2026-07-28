<?php

namespace Tests\Unit\Services\GiftCards;

use App\Services\GiftCards\GiftCardEmitService;
use PHPUnit\Framework\TestCase;
use stdClass;

class GiftCardEmitServiceTest extends TestCase
{
    public function test_ignora_lineas_no_gift(): void
    {
        $persistCalls = 0;

        $svc = new GiftCardEmitService(
            tieneFuncionalidad: fn () => true,
            obtenerConfig: fn () => ['id_categoria_gift_cards' => 99],
            persistirGiftCard: function () use (&$persistCalls) {
                $persistCalls++;

                return new stdClass();
            },
            generarCodigo: fn () => 'GC0000000001'
        );

        $producto = (object) ['id_categoria' => 10];
        $detalle = (object) [
            'id' => 100,
            'id_producto' => 5,
            'total' => 50.0,
            'id_vendedor' => null,
            'producto' => $producto,
        ];
        $venta = (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 3,
            'detalles' => [$detalle],
        ];

        $svc->emitirDesdeVenta($venta);

        $this->assertSame(0, $persistCalls);
    }
}
