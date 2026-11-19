<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaInvoiceFromVentaMapper;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaReciboPagoMapper;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CostaRicaReciboPagoMapperTest extends TestCase
{
    private CostaRicaReciboPagoMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CostaRicaReciboPagoMapper(
            new CostaRicaInvoiceFromVentaMapper(
                new CostaRicaTipoCambioService($this->createMock(BccrTipoCambioClient::class))
            )
        );
    }

    public function test_referencia_factura_01_usa_codigo_17_y_clave(): void
    {
        $clave = str_repeat('5', 50);
        $ref = $this->mapper->referenciaDesdeVenta($this->venta([
            'tipo_dte' => '01',
            'codigo_generacion' => $clave,
            'sello_mh' => $clave,
            'fecha' => '2026-09-15 09:00:00',
        ]));

        self::assertSame('01', $ref['document_type']);
        self::assertSame($clave, $ref['document_number']);
        self::assertSame(ReferenciaComprobanteCr::CODIGO_PAGO_REP, $ref['referenced_code']);
    }

    public function test_nota_de_credito_no_es_referenciable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->referenciaDesdeVenta($this->venta([
            'tipo_dte' => '03',
            'codigo_generacion' => str_repeat('1', 50),
            'fecha' => '2026-09-15 09:00:00',
        ]));
    }

    public function test_sin_clave_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->mapper->referenciaDesdeVenta($this->venta([
            'tipo_dte' => '01',
            'codigo_generacion' => '',
            'sello_mh' => '',
            'fecha' => '2026-09-15 09:00:00',
        ]));
    }

    public function test_medio_pago_desde_forma(): void
    {
        self::assertSame('01', $this->mapper->medioPagoDesdeForma('Efectivo'));
        self::assertSame('02', $this->mapper->medioPagoDesdeForma('Tarjeta'));
        self::assertSame('04', $this->mapper->medioPagoDesdeForma('Transferencia'));
        self::assertSame('06', $this->mapper->medioPagoDesdeForma('SINPE Móvil'));
    }

    public function test_tipo_comprobante_rep_es_10_y_referencia_rep_es_20(): void
    {
        self::assertSame('10', ReferenciaComprobanteCr::TIPO_COMPROBANTE_REP);
        self::assertSame('20', ReferenciaComprobanteCr::TIPO_RECIBO_PAGO);
        self::assertSame('17', ReferenciaComprobanteCr::CODIGO_PAGO_REP);
    }

    private function venta(array $attrs): Venta
    {
        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRawAttributes($attrs, true);

        return $venta;
    }
}
