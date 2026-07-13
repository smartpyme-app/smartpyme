<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Services\FacturacionElectronica\CostaRica\CostaRicaCreditNoteFromDevolucionMapper;
use PHPUnit\Framework\TestCase;

/**
 * Hacienda -17: la identificación del receptor de la nota de crédito debe coincidir con la del
 * comprobante original. Se verifica que se tome del XML firmado emitido y no del cliente actual.
 */
final class CostaRicaCreditNoteReceptorAlineadoTest extends TestCase
{
    private function xmlFacturaConReceptor(string $tipo, string $numero): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<FacturaElectronica xmlns="https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4/facturaElectronica">
  <NumeroConsecutivo>00100001010000000001</NumeroConsecutivo>
  <Receptor>
    <Nombre>Cliente Original</Nombre>
    <Identificacion>
      <Tipo>{$tipo}</Tipo>
      <Numero>{$numero}</Numero>
    </Identificacion>
  </Receptor>
</FacturaElectronica>
XML;
    }

    public function test_usa_identificacion_del_receptor_original_no_la_del_cliente_actual(): void
    {
        // El cliente hoy tiene NIT (tipo 02); la factura original se emitió a receptor genérico (tipo 06).
        $receiverActual = [
            'identification_type' => '02',
            'identification_number' => '310450678',
            'name' => 'Cliente Editado',
        ];

        $alineado = CostaRicaCreditNoteFromDevolucionMapper::alinearReceptorConComprobanteOriginal(
            $receiverActual,
            $this->xmlFacturaConReceptor('06', '00000000000000')
        );

        $this->assertSame('06', $alineado['identification_type']);
        $this->assertSame('00000000000000', $alineado['identification_number']);
        // Conserva el resto del bloque recalculado (nombre/ubicación) para el generador XML.
        $this->assertSame('Cliente Editado', $alineado['name']);
    }

    public function test_sin_xml_original_conserva_el_receptor_recalculado(): void
    {
        $receiverActual = [
            'identification_type' => '02',
            'identification_number' => '310450678',
        ];

        $alineado = CostaRicaCreditNoteFromDevolucionMapper::alinearReceptorConComprobanteOriginal(
            $receiverActual,
            null
        );

        $this->assertSame($receiverActual, $alineado);
    }

    public function test_xml_ilegible_conserva_el_receptor_recalculado(): void
    {
        $receiverActual = [
            'identification_type' => '01',
            'identification_number' => '109990888',
        ];

        $alineado = CostaRicaCreditNoteFromDevolucionMapper::alinearReceptorConComprobanteOriginal(
            $receiverActual,
            '<no-es-un-comprobante/>'
        );

        $this->assertSame($receiverActual, $alineado);
    }
}
