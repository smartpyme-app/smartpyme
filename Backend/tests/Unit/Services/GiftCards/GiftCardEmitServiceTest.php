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

    public function test_monto_incluye_iva_del_detalle(): void
    {
        $saved = null;

        $svc = new GiftCardEmitService(
            tieneFuncionalidad: fn () => true,
            obtenerConfig: fn () => ['id_categoria_gift_cards' => 99],
            persistirGiftCard: function (array $where, array $values) use (&$saved) {
                $saved = $values;

                return (object) $values;
            },
            generarCodigo: fn () => 'GC0000000001'
        );

        $producto = (object) ['id_categoria' => 99];
        $detalle = (object) [
            'id' => 100,
            'id_producto' => 5,
            'total' => 26.55,
            'iva' => 3.45,
            'id_vendedor' => 7,
            'producto' => $producto,
        ];
        $venta = (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 3,
            'detalles' => [$detalle],
        ];

        $svc->emitirDesdeVenta($venta);

        $this->assertNotNull($saved);
        $this->assertSame(30.0, (float) $saved['monto_inicial']);
        $this->assertSame(30.0, (float) $saved['saldo']);
    }
}
