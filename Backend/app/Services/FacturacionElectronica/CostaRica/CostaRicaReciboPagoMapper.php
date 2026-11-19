<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Abono;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use InvalidArgumentException;

/**
 * Arma el payload del Recibo Electrónico de Pago (tipo comprobante 10, código referencia 17).
 */
final class CostaRicaReciboPagoMapper
{
    public function __construct(
        private readonly CostaRicaInvoiceFromVentaMapper $invoiceMapper,
    ) {}

    /**
     * @return array{document_type: string, document_number: string, emission_date: string, referenced_code: string, reason: string}
     */
    public function referenciaDesdeVenta(Venta $venta, string $razon = 'Abono a comprobante electrónico'): array
    {
        $clave = trim((string) ($venta->codigo_generacion ?: $venta->sello_mh ?: ''));
        if ($clave === '') {
            throw new InvalidArgumentException('La venta no tiene clave de comprobante electrónico para referenciar en el REP.');
        }

        $tipo = $this->tipoDocumentoReferenciaParaRep((string) ($venta->tipo_dte ?? ''));
        $fecha = ReferenciaComprobanteCr::fechaEmisionDesdeClave($clave)
            ?? \Carbon\Carbon::parse($venta->fecha)->timezone('America/Costa_Rica')->format('Y-m-d\TH:i:sP');

        return [
            'document_type' => $tipo,
            'document_number' => $clave,
            'emission_date' => $fecha,
            'referenced_code' => ReferenciaComprobanteCr::CODIGO_PAGO_REP,
            'reason' => mb_substr(strip_tags($razon), 0, 180),
        ];
    }

    public function tipoDocumentoReferenciaParaRep(string $tipoDteVenta): string
    {
        $tipo = trim($tipoDteVenta);
        if ($tipo === '') {
            throw new InvalidArgumentException('La venta no tiene tipo de comprobante (tipo_dte) para el REP.');
        }

        // FEE se guarda como 09; en Nota 10 la referencia es 19, pero el REP no permite referenciar FEE.
        if (! in_array($tipo, ReferenciaComprobanteCr::tiposPermitidosEnReciboPago(), true)) {
            throw new InvalidArgumentException(
                "El recibo electrónico de pago no puede referenciar el tipo de documento {$tipo}."
            );
        }

        return $tipo;
    }

    public function medioPagoDesdeForma(?string $formaPago): string
    {
        $f = mb_strtolower(trim((string) $formaPago), 'UTF-8');
        if ($f === '') {
            return '01';
        }
        if (str_contains($f, 'tarjeta') || str_contains($f, 'card')) {
            return '02';
        }
        if (str_contains($f, 'cheque')) {
            return '03';
        }
        if (str_contains($f, 'sinpe')) {
            return '06';
        }
        if (str_contains($f, 'transfer') || str_contains($f, 'depósito') || str_contains($f, 'deposito') || str_contains($f, 'banco')) {
            return '04';
        }
        if (str_contains($f, 'digital') || str_contains($f, 'wompi') || str_contains($f, 'n1co')) {
            return '07';
        }

        return '01';
    }

    /**
     * Condición de venta del REP: 11 (pago a crédito IVA 90 días) o 09 (pago servicios al Estado).
     * ponytail: default 11; 09 solo si la factura original usó condición 08.
     */
    public function condicionVentaRep(Venta $venta): string
    {
        $c = strtolower(trim((string) ($venta->condicion ?? '')));
        if (str_contains($c, 'estado') || (string) ($venta->condicion_venta_fe ?? '') === '08') {
            return '09';
        }

        return '11';
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Abono $abono, Venta $venta, Empresa $empresa, int $secuencial): array
    {
        $monto = round((float) $abono->total, 5);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del abono debe ser mayor a cero para emitir el REP.');
        }

        $venta->loadMissing(['cliente', 'sucursal', 'detalles.producto']);
        $referencia = $this->referenciaDesdeVenta(
            $venta,
            (string) ($abono->concepto ?: 'Abono a comprobante electrónico')
        );

        $header = $this->invoiceMapper->encabezadoDocumento(
            $empresa,
            $this->invoiceMapper->fechaEmisionXmlCr(),
            $secuencial,
            $this->condicionVentaRep($venta),
            $venta->sucursal,
            (string) ($venta->currency_code ?? $empresa->moneda ?? 'CRC'),
            $venta->exchange_rate
        );

        $cabys = $this->cabysParaRep($venta, $empresa);
        $descripcion = mb_substr(
            strip_tags((string) ($abono->concepto ?: ('Abono a '.$venta->nombre_documento.' #'.$venta->correlativo))),
            0,
            200
        );

        $line = [
            'cabys_code' => $cabys,
            'description' => $descripcion !== '' ? $descripcion : 'Abono a comprobante electrónico',
            'quantity' => 1.0,
            'unit_measure' => 'Unid',
            'unit_price' => $monto,
            'sub_total' => $monto,
            'total_amount' => $monto,
            'taxable_base' => $monto,
            'transaction_type' => '01',
            'total_tax' => 0.0,
            'total' => $monto,
            'taxes' => [],
        ];

        $medio = $this->medioPagoDesdeForma($abono->forma_pago ?? null);

        return array_merge($header, [
            'issuer' => $this->invoiceMapper->emisorDatos($empresa, $venta->sucursal),
            'receiver' => $this->invoiceMapper->receptorDatosVenta($venta, $empresa),
            'line_items' => [$line],
            'payments' => [[
                'payment_method' => $medio,
                'amount' => round($monto, 2),
            ]],
            'summary' => [
                'total_taxed_goods' => 0.0,
                'total_exempt_goods' => $monto,
                'total_non_taxable_goods' => 0.0,
                'total_taxed_services' => 0.0,
                'total_exempt_services' => 0.0,
                'total_non_taxable_services' => 0.0,
                'total_taxed' => 0.0,
                'total_exempt' => $monto,
                'total_non_taxable' => 0.0,
                'total_sale' => $monto,
                'total_discounts' => 0.0,
                'total_net_sale' => $monto,
                'total_tax' => 0.0,
                'total' => round($monto, 2),
                'taxes' => [],
            ],
            'referenced_documents' => [$referencia],
        ]);
    }

    private function cabysParaRep(Venta $venta, Empresa $empresa): string
    {
        $venta->loadMissing('detalles.producto');
        foreach ($venta->detalles ?? [] as $detalle) {
            $producto = $detalle->producto ?? null;
            $raw = preg_replace('/\D/', '', (string) ($producto->cabys ?? $producto->codigo_cabys ?? ''));
            if (strlen((string) $raw) === 13) {
                return (string) $raw;
            }
        }

        $default = preg_replace('/\D/', '', (string) $empresa->getCustomConfigValue('facturacion_fe', 'cabys_default', null));
        if (strlen((string) $default) === 13) {
            return (string) $default;
        }

        throw new InvalidArgumentException(
            'Configure CABYS en el producto de la venta o facturacion_fe.cabys_default (13 dígitos) para emitir el REP.'
        );
    }
}
