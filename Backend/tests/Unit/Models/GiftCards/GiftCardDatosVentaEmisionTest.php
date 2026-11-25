<?php

namespace Tests\Unit\Models\GiftCards;

use App\Models\GiftCards\GiftCard;
use App\Models\Ventas\Venta;
use Tests\TestCase;

class GiftCardDatosVentaEmisionTest extends TestCase
{
    public function test_sin_venta_emision_devuelve_nulos(): void
    {
        $card = new GiftCard();

        $this->assertSame(
            ['fecha_venta' => null, 'numero_factura' => null],
            $card->datosVentaEmision()
        );
    }

    public function test_con_venta_expone_fecha_y_correlativo(): void
    {
        $venta = new Venta();
        $venta->fecha = '2026-03-10 14:30:00';
        $venta->correlativo = 'FAC-00042';

        $card = new GiftCard();
        $card->setRelation('ventaEmision', $venta);

        $this->assertSame(
            [
                'fecha_venta' => '2026-03-10 14:30:00',
                'numero_factura' => 'FAC-00042',
            ],
            $card->datosVentaEmision()
        );
    }

    public function test_correlativo_vacio_se_expone_como_null(): void
    {
        $venta = new Venta();
        $venta->fecha = '2026-03-10 14:30:00';
        $venta->correlativo = '';

        $card = new GiftCard();
        $card->setRelation('ventaEmision', $venta);

        $this->assertNull($card->datosVentaEmision()['numero_factura']);
    }
}