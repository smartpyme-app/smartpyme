<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\CostaRicaXmlComprobantePdfMapper;
use PHPUnit\Framework\TestCase;

/**
 * NumeroConsecutivo CR (20): establecimiento(3) + terminal(5) + tipo(2) + secuencial(10).
 * El PDF debe conservar el tipo de comprobante (p. ej. 04 tiquete), no solo est-terminal-seq.
 */
final class CostaRicaXmlComprobantePdfMapperTest extends TestCase
{
    public function test_partes_del_consecutivo_incluyen_tipo_de_comprobante(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<TiqueteElectronico xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/tiqueteElectronico">
  <NumeroConsecutivo>00100001040001372308</NumeroConsecutivo>
  <FechaEmision>2026-04-02T22:48:44-06:00</FechaEmision>
</TiqueteElectronico>
XML;

        $doc = CostaRicaXmlComprobantePdfMapper::fromXml($xml);

        $this->assertSame('001', $doc['establishment']);
        $this->assertSame('00001', $doc['emission_point']);
        $this->assertSame('04', $doc['document_type_code']);
        $this->assertSame('0001372308', $doc['sequential']);
    }

    public function test_formato_consecutivo_para_mostrar_incluye_tipo(): void
    {
        $fmt = CostaRicaXmlComprobantePdfMapper::formatoConsecutivoParaMostrar([
            'establishment' => '001',
            'emission_point' => '00001',
            'document_type_code' => '04',
            'sequential' => '0001372308',
        ]);

        $this->assertSame('001-00001-04-0001372308', $fmt);
    }

    public function test_formato_consecutivo_usa_fallback_de_tipo_si_falta_en_documento(): void
    {
        $fmt = CostaRicaXmlComprobantePdfMapper::formatoConsecutivoParaMostrar([
            'establishment' => '001',
            'emission_point' => '00001',
            'sequential' => '0000000001',
        ], '01');

        $this->assertSame('001-00001-01-0000000001', $fmt);
    }
}
