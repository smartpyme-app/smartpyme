<?php

namespace Tests\Unit\Services\Compras\DocumentoImport;

use App\Services\Compras\DocumentoImport\CostaRicaXmlDocumentoParser;
use PHPUnit\Framework\TestCase;

class CostaRicaXmlDocumentoParserTest extends TestCase
{
    private CostaRicaXmlDocumentoParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CostaRicaXmlDocumentoParser;
    }

    public function test_parse_xml_factura_cr(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica">
  <Clave>50624051000010123456789012345678901234567890123456</Clave>
  <NumeroConsecutivo>00100001010000000001</NumeroConsecutivo>
  <FechaEmision>2024-05-15T08:30:00-06:00</FechaEmision>
  <Emisor>
    <Nombre>Proveedor CR S.A.</Nombre>
    <Identificacion>
      <Tipo>02</Tipo>
      <Numero>3101123456</Numero>
    </Identificacion>
    <CorreoElectronico>facturas@proveedor.cr</CorreoElectronico>
  </Emisor>
  <DetalleServicio>
    <LineaDetalle>
      <NumeroLinea>1</NumeroLinea>
      <Codigo>
        <Tipo>04</Tipo>
        <Codigo>1234567890123</Codigo>
      </Codigo>
      <Detalle>Servicio consultoría</Detalle>
      <Cantidad>1</Cantidad>
      <PrecioUnitario>100000</PrecioUnitario>
      <SubTotal>100000</SubTotal>
      <MontoTotal>100000</MontoTotal>
      <Impuesto>
        <Codigo>01</Codigo>
        <Monto>13000</Monto>
      </Impuesto>
    </LineaDetalle>
  </DetalleServicio>
  <ResumenFactura>
    <TotalGravado>100000</TotalGravado>
    <TotalVenta>100000</TotalVenta>
    <TotalImpuesto>13000</TotalImpuesto>
    <TotalComprobante>113000</TotalComprobante>
  </ResumenFactura>
</FacturaElectronica>
XML;

        $this->assertTrue($this->parser->supports($xml));
        $dto = $this->parser->parse($xml);

        $this->assertSame('CR', $dto->pais);
        $this->assertSame('xml', $dto->formatoOrigen);
        $this->assertSame('2024-05-15', $dto->identificacion['fechaEmision']);
        $this->assertSame('01', $dto->identificacion['tipoDocumento']);
        $this->assertSame('3101123456', $dto->emisor['nit']);
        $this->assertCount(1, $dto->lineas);
        $this->assertSame('1234567890123', $dto->lineas[0]['codigo']);

        $mh = $dto->toMhCompatArray();
        $this->assertSame('50624051000010123456789012345678901234567890123456', $mh['identificacion']['codigoGeneracion']);
        $this->assertSame(13000.0, $mh['resumen']['tributos'][0]['valor']);
    }

    public function test_parse_xml_con_otros_cargos_propina(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica">
  <Clave>50611052600310174405500176401010000007844111052601</Clave>
  <NumeroConsecutivo>00100001010000000078</NumeroConsecutivo>
  <FechaEmision>2026-05-11T13:41:00-06:00</FechaEmision>
  <Emisor>
    <Nombre>Restaurante Test</Nombre>
    <Identificacion><Tipo>02</Tipo><Numero>3101744055</Numero></Identificacion>
  </Emisor>
  <DetalleServicio>
    <LineaDetalle>
      <NumeroLinea>1</NumeroLinea>
      <Detalle>Almuerzo</Detalle>
      <Cantidad>1</Cantidad>
      <PrecioUnitario>6000</PrecioUnitario>
      <SubTotal>6000</SubTotal>
      <MontoTotal>6000</MontoTotal>
    </LineaDetalle>
  </DetalleServicio>
  <OtrosCargos>
    <TipoDocumentoOC>06</TipoDocumentoOC>
    <Detalle>Impuesto de servicio 10%</Detalle>
    <MontoCargo>600.00</MontoCargo>
  </OtrosCargos>
  <ResumenFactura>
    <TotalGravado>6000</TotalGravado>
    <TotalVenta>6000</TotalVenta>
    <TotalImpuesto>780</TotalImpuesto>
    <TotalOtrosCargos>600</TotalOtrosCargos>
    <TotalComprobante>7380</TotalComprobante>
  </ResumenFactura>
</FacturaElectronica>
XML;

        $dto = $this->parser->parse($xml);
        $this->assertCount(1, $dto->lineas);
        $this->assertSame('Almuerzo', $dto->lineas[0]['descripcion']);
        $this->assertSame(600.0, $dto->resumen['totalOtrosCargos']);
        $mh = $dto->toMhCompatArray();
        $this->assertCount(1, $mh['cuerpoDocumento']);
        $this->assertSame(600.0, $mh['resumen']['totalOtrosCargos']);
        $this->assertSame(7380.0, $mh['resumen']['totalPagar']);
    }

    public function test_parse_xml_con_descuento_en_linea(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica">
  <Clave>50624051000010123456789012345678901234567890123457</Clave>
  <NumeroConsecutivo>00100001010000000002</NumeroConsecutivo>
  <FechaEmision>2024-05-16T10:00:00-06:00</FechaEmision>
  <Emisor>
    <Nombre>Proveedor CR</Nombre>
    <Identificacion><Tipo>02</Tipo><Numero>3101123456</Numero></Identificacion>
  </Emisor>
  <DetalleServicio>
    <LineaDetalle>
      <NumeroLinea>1</NumeroLinea>
      <Detalle>Producto con descuento</Detalle>
      <Cantidad>2</Cantidad>
      <PrecioUnitario>1000</PrecioUnitario>
      <MontoTotal>2000</MontoTotal>
      <Descuento>
        <MontoDescuento>200</MontoDescuento>
        <CodigoDescuento>06</CodigoDescuento>
      </Descuento>
      <SubTotal>1800</SubTotal>
      <Impuesto><Codigo>01</Codigo><Monto>234</Monto></Impuesto>
    </LineaDetalle>
  </DetalleServicio>
  <ResumenFactura>
    <TotalGravado>1800</TotalGravado>
    <TotalDescuentos>200</TotalDescuentos>
    <TotalImpuesto>234</TotalImpuesto>
    <TotalComprobante>2034</TotalComprobante>
  </ResumenFactura>
</FacturaElectronica>
XML;

        $dto = $this->parser->parse($xml);
        $this->assertCount(1, $dto->lineas);
        $this->assertSame(200.0, $dto->lineas[0]['descuento']);
        $mh = $dto->toMhCompatArray();
        $this->assertSame(200.0, $mh['cuerpoDocumento'][0]['montoDescu']);
    }

    public function test_rechaza_xml_respuesta_hacienda(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<MensajeHacienda xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/mensajeHacienda">
  <Clave>50611052600310174405500176401010000007844111052601</Clave>
  <TotalFactura>7509.65</TotalFactura>
</MensajeHacienda>
XML;

        $this->expectException(\App\Exceptions\Compras\DocumentoImportException::class);
        $this->parser->parse($xml);
    }
}
