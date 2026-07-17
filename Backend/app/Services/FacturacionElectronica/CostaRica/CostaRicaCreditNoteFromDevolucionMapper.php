<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\CostaRicaFeDteDocumento;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class CostaRicaCreditNoteFromDevolucionMapper
{
    public function __construct(
        private readonly CostaRicaInvoiceFromVentaMapper $invoiceMapper,
    ) {}

    public function buildDocumentData(Devolucion $devolucion, Empresa $empresa, Venta $facturaOriginal, int $secuencialNc): array
    {
        $devolucion->loadMissing(['detalles.producto.impuestos', 'cliente', 'sucursal']);

        if ($devolucion->detalles->isEmpty()) {
            throw new InvalidArgumentException('La devolución no tiene líneas de detalle.');
        }

        $claveFactura = trim((string) ($facturaOriginal->codigo_generacion ?? ''));
        if ($claveFactura === '') {
            throw new InvalidArgumentException('La factura original no tiene clave electrónica (codigo_generacion).');
        }

        $fechaFactura = Carbon::parse($facturaOriginal->fecha)->timezone('America/Costa_Rica')->format('Y-m-d\TH:i:sP');

        $saleCond = '01';
        $header = $this->invoiceMapper->encabezadoDocumento(
            $empresa,
            $this->invoiceMapper->fechaEmisionXmlCr(),
            $secuencialNc,
            $saleCond,
            $devolucion->sucursal
        );

        $facturaOriginal->loadMissing('cliente');
        $receiver = $this->invoiceMapper->receptorDatosVenta($facturaOriginal, $empresa);
        // Hacienda -17: la identificación del receptor de la NC debe ser idéntica a la del comprobante original.
        // El cliente pudo editarse tras emitir la factura (p. ej. genérico 06 → NIT 02); tomar la del XML firmado.
        $receiver = self::alinearReceptorConComprobanteOriginal(
            $receiver,
            CostaRicaFeDteDocumento::xmlComprobanteEmitido($facturaOriginal->dte)
        );

        $pctIva = 0.0;
        $sub = (float) ($devolucion->sub_total ?? 0);
        $ivaH = (float) ($devolucion->iva ?? 0);
        if ($sub > 0 && $ivaH > 0) {
            $pctIva = round(100 * $ivaH / $sub, 2);
        }

        $lineItems = array_values($devolucion->detalles->map(function ($d) use ($empresa, $pctIva) {
            return $this->invoiceMapper->lineaDesdeDetalleDevolucion($d, $empresa, $pctIva);
        })->all());

        $referenced = [[
            'document_type' => '01',
            'document_number' => $claveFactura,
            'emission_date' => $fechaFactura,
            'referenced_code' => '06',
            'reason' => mb_substr(strip_tags((string) ($devolucion->observaciones ?: 'Devolución de mercancía')), 0, 180),
        ]];

        return array_merge($header, [
            'issuer' => $this->invoiceMapper->emisorDatos($empresa),
            'receiver' => $receiver,
            'line_items' => $lineItems,
            'payments' => $this->invoiceMapper->pagosDesdeLineas($lineItems),
            'summary' => $this->invoiceMapper->resumenDevolucionAlineadoLineas($devolucion, $lineItems),
            'referenced_documents' => $referenced,
        ]);
    }

    /**
     * Fuerza que Tipo/Numero de identificación del receptor coincidan con los del comprobante original firmado
     * (evita el rechazo -17 de Hacienda). Conserva el resto del bloque receptor recalculado (nombre, ubicación).
     * Si no hay XML original, es ilegible o no trae identificación de receptor, devuelve el receptor sin cambios.
     *
     * @param  array<string, mixed>  $receiver
     * @return array<string, mixed>
     */
    public static function alinearReceptorConComprobanteOriginal(array $receiver, ?string $xmlOriginal): array
    {
        if (! is_string($xmlOriginal) || trim($xmlOriginal) === '') {
            return $receiver;
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $cargado = $dom->loadXML($xmlOriginal);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $cargado) {
            return $receiver;
        }

        $receptor = $dom->getElementsByTagName('Receptor')->item(0);
        if (! $receptor instanceof DOMElement) {
            return $receiver;
        }

        $identificacion = $receptor->getElementsByTagName('Identificacion')->item(0);
        if (! $identificacion instanceof DOMElement) {
            return $receiver;
        }

        $tipo = self::textoNodoHijo($identificacion, 'Tipo');
        $numero = self::textoNodoHijo($identificacion, 'Numero');
        if ($tipo === '' || $numero === '') {
            return $receiver;
        }

        return array_replace($receiver, [
            'identification_type' => $tipo,
            'identification_number' => $numero,
        ]);
    }

    private static function textoNodoHijo(DOMElement $padre, string $tag): string
    {
        $nodo = $padre->getElementsByTagName($tag)->item(0);

        return $nodo === null ? '' : trim($nodo->textContent);
    }
}
