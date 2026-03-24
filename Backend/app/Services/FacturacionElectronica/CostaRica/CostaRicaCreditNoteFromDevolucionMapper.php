<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use InvalidArgumentException;

final class CostaRicaCreditNoteFromDevolucionMapper
{
    public function __construct(
        private readonly CostaRicaInvoiceFromVentaMapper $invoiceMapper,
    ) {}

    public function buildDocumentData(Devolucion $devolucion, Empresa $empresa, Venta $facturaOriginal, int $secuencialNc): array
    {
        $devolucion->loadMissing(['detalles.producto', 'cliente']);

        if ($devolucion->detalles->isEmpty()) {
            throw new InvalidArgumentException('La devolución no tiene líneas de detalle.');
        }

        $claveFactura = trim((string) ($facturaOriginal->codigo_generacion ?? ''));
        if ($claveFactura === '') {
            throw new InvalidArgumentException('La factura original no tiene clave electrónica (codigo_generacion).');
        }

        $fechaFactura = Carbon::parse($facturaOriginal->fecha)->timezone('America/Costa_Rica')->format('Y-m-d\TH:i:sP');

        $saleCond = '01';
        $header = $this->invoiceMapper->encabezadoDocumento($empresa, (string) $devolucion->fecha, $secuencialNc, $saleCond);

        $facturaOriginal->loadMissing('cliente');
        $receiver = $this->invoiceMapper->receptorDatosVenta($facturaOriginal, $empresa);

        $pctIva = 0.0;
        $sub = (float) ($devolucion->sub_total ?? 0);
        $ivaH = (float) ($devolucion->iva ?? 0);
        if ($sub > 0 && $ivaH > 0) {
            $pctIva = round(100 * $ivaH / $sub, 2);
        }

        $lineItems = $devolucion->detalles->map(function ($d) use ($empresa, $pctIva) {
            return $this->invoiceMapper->lineaDesdeDetalleDevolucion($d, $empresa, $pctIva);
        })->all();

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
            'payments' => $this->invoiceMapper->pagosDesdeMonto((float) $devolucion->total),
            'summary' => $this->invoiceMapper->resumenDesdeDevolucion($devolucion),
            'referenced_documents' => $referenced,
        ]);
    }
}
