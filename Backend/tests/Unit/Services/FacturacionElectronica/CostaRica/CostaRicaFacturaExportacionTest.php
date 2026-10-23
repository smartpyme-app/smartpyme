<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaInvoiceFromVentaMapper;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use DazzaDev\DgtXmlGenerator\DataLoader;
use DazzaDev\DgtXmlGenerator\Factories\DocumentBuilderFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CostaRicaFacturaExportacionTest extends TestCase
{
    public function test_factura_de_exportacion_usa_tipo_de_comprobante_de_exportacion(): void
    {
        self::assertSame('09', CostaRicaFeEmitService::tipoDocumentoExportacion('Factura de exportación'));
        self::assertSame('09', CostaRicaFeEmitService::tipoDocumentoExportacion('Factura de exportacion'));

        $emision = CostaRicaFeEmitService::parametrosEmisionFactura('Factura de exportación');

        self::assertSame('export-invoice', $emision['dgt_type']);
        self::assertSame('09', $emision['tipo_dte']);
    }

    public function test_factura_nacional_sigue_siendo_tipo_01(): void
    {
        self::assertNull(CostaRicaFeEmitService::tipoDocumentoExportacion('Factura'));

        $emision = CostaRicaFeEmitService::parametrosEmisionFactura('Factura');

        self::assertSame('invoice', $emision['dgt_type']);
        self::assertSame('01', $emision['tipo_dte']);
    }

    public function test_referencia_a_fee_usa_tipo_19_y_clave_de_50(): void
    {
        $clave = str_repeat('5', 50);

        $ref = CostaRicaFeEmitService::referenciaNotaSobreComprobante('09', $clave);

        self::assertSame(ReferenciaComprobanteCr::TIPO_EXPORTACION, $ref['document_type']);
        self::assertSame('19', $ref['document_type']);
        self::assertSame($clave, $ref['document_number']);
        self::assertSame(50, strlen($ref['document_number']));
    }

    public function test_referencia_fee_sin_clave_lanza_excepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CostaRicaFeEmitService::referenciaNotaSobreComprobante('09', '');
    }

    public function test_factura_nacional_no_referencia_tipo_19(): void
    {
        $clave = str_repeat('8', 50);

        $ref = CostaRicaFeEmitService::referenciaNotaSobreComprobante('01', $clave);

        self::assertSame('01', $ref['document_type']);
        self::assertNotSame('19', $ref['document_type']);
    }

    public function test_bien_en_fee_sin_partida_falla_nombrando_el_producto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Café molido');

        CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(true, '0123456789012', null, 'Café molido');
    }

    public function test_bien_en_fee_exige_partida_de_12_digitos(): void
    {
        self::assertSame(
            '123456789012',
            CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(true, '4012345678901', '123456789012', 'Café')
        );

        self::assertSame(
            '060220100000',
            CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(true, '4012345678901', '0602.20.10.00.00', 'Shampoo')
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Azúcar');

        CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(true, '2012345678901', '12345', 'Azúcar');
    }

    public function test_fee_sin_cliente_no_usa_receptor_generico(): void
    {
        $mapper = new CostaRicaInvoiceFromVentaMapper(
            new CostaRicaTipoCambioService($this->createMock(BccrTipoCambioClient::class))
        );
        $empresa = (new \ReflectionClass(\App\Models\Admin\Empresa::class))->newInstanceWithoutConstructor();
        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRelation('cliente', null);

        $method = new \ReflectionMethod(CostaRicaInvoiceFromVentaMapper::class, 'receptor');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no se permite consumidor final');

        $method->invoke($mapper, $venta, $empresa, true);
    }

    public function test_servicio_y_factura_nacional_no_ganan_partida_arancelaria(): void
    {
        self::assertNull(CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(true, '5123456789012', null, 'Consultoría'));
        self::assertNull(CostaRicaInvoiceFromVentaMapper::partidaArancelariaDeLinea(false, '0123456789012', '123456789012', 'Café'));
    }

    public function test_el_generador_acepta_el_tipo_factura_de_exportacion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field');

        DocumentBuilderFactory::create('export-invoice', []);
    }

    public function test_el_catalogo_de_referencia_conoce_el_tipo_19(): void
    {
        $tipo = (new DataLoader('tipos-documentos-referencia'))->getByCode(ReferenciaComprobanteCr::TIPO_EXPORTACION);

        self::assertSame('19', $tipo['code']);
    }
}
