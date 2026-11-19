<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\CostaRicaXmlComprobantePdfMapper;
use PHPUnit\Framework\TestCase;

final class CostaRicaXmlComprobantePdfMapperRepTest extends TestCase
{
    public function test_recibo_electronico_pago_mapea_pdf(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ReciboElectronicoPago xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/reciboElectronicoPago">
  <Clave>50615092600310123456700100001010000000001199999999</Clave>
  <NumeroConsecutivo>00100001010000000001</NumeroConsecutivo>
  <FechaEmision>2026-11-19T15:00:00-06:00</FechaEmision>
  <Emisor>
    <Nombre>Emisor SA</Nombre>
    <Identificacion><Tipo>02</Tipo><Numero>3101234567</Numero></Identificacion>
  </Emisor>
  <Receptor>
    <Nombre>Cliente</Nombre>
    <Identificacion><Tipo>01</Tipo><Numero>101230456</Numero></Identificacion>
  </Receptor>
  <CondicionVenta>02</CondicionVenta>
  <DetalleServicio>
    <LineaDetalle>
      <NumeroLinea>1</NumeroLinea>
      <Detalle>Abono a factura</Detalle>
      <MontoTotal>100.00000</MontoTotal>
      <SubTotal>100.00000</SubTotal>
      <ImpuestoNeto>0.00000</ImpuestoNeto>
      <MontoTotalLinea>100.00000</MontoTotalLinea>
    </LineaDetalle>
  </DetalleServicio>
  <ResumenFactura>
    <CodigoTipoMoneda>
      <CodigoMoneda>CRC</CodigoMoneda>
      <TipoCambio>1.00000</TipoCambio>
    </CodigoTipoMoneda>
    <TotalVenta>100.00</TotalVenta>
    <TotalVentaNeta>100.00</TotalVentaNeta>
    <MedioPago>
      <TipoMedioPago>01</TipoMedioPago>
      <TotalMedioPago>100.00</TotalMedioPago>
    </MedioPago>
    <TotalComprobante>100.00</TotalComprobante>
  </ResumenFactura>
</ReciboElectronicoPago>
XML;

        $doc = CostaRicaXmlComprobantePdfMapper::fromXml($xml);

        self::assertSame('Abono a factura', $doc['line_items'][0]['description'] ?? null);
        self::assertSame(100.0, (float) ($doc['summary']['total'] ?? 0));
        self::assertSame('01', $doc['payments'][0]['payment_method'] ?? null);
        self::assertSame(100.0, (float) ($doc['payments'][0]['amount'] ?? 0));
        self::assertSame('CRC', $doc['currency']['currency_code'] ?? null);
    }
}
