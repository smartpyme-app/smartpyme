<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\CostaRicaFeDteDocumento;
use PHPUnit\Framework\TestCase;

final class CostaRicaFeDteDocumentoTest extends TestCase
{
    public function test_tiene_comprobante_cr_con_xml_directo(): void
    {
        $xml = '<?xml version="1.0"?><TiqueteElectronico></TiqueteElectronico>';

        $this->assertTrue(CostaRicaFeDteDocumento::tieneComprobanteCr($xml));
    }

    public function test_tiene_comprobante_cr_con_documento_en_array_sin_pais(): void
    {
        $xml = '<?xml version="1.0"?><FacturaElectronica></FacturaElectronica>';

        $this->assertTrue(CostaRicaFeDteDocumento::tieneComprobanteCr(['documento' => $xml]));
    }

    public function test_documento_para_pdf_con_payload_interno(): void
    {
        $payload = [
            'line_items' => [],
            'summary' => ['total' => 100],
            'currency' => ['currency_code' => 'CRC'],
        ];
        $dte = ['cr' => ['payload_interno' => $payload]];

        $this->assertTrue(CostaRicaFeDteDocumento::tieneComprobanteCr($dte));
        $this->assertSame($payload, CostaRicaFeDteDocumento::documentoParaPdf($dte));
    }

    public function test_tiene_emision_registrada_con_sello_mh_como_sv(): void
    {
        $this->assertTrue(CostaRicaFeDteDocumento::tieneEmisionRegistrada(null, '50612345678901234567890123456789012345678901234567', null));
        $this->assertSame(
            '50612345678901234567890123456789012345678901234567',
            CostaRicaFeDteDocumento::claveEmision(null, '50612345678901234567890123456789012345678901234567')
        );
    }

    public function test_clave_emision_prefiere_codigo_generacion(): void
    {
        $this->assertSame('CLAVE-A', CostaRicaFeDteDocumento::claveEmision('CLAVE-A', 'SELLO-B'));
    }

    public function test_tiene_comprobante_cr_rechaza_null(): void
    {
        $this->assertFalse(CostaRicaFeDteDocumento::tieneComprobanteCr(null));
    }
}
