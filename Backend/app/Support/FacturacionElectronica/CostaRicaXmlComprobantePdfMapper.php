<?php

namespace App\Support\FacturacionElectronica;

use App\Services\Compras\DocumentoImport\Support\XmlLocalNameHelper;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;

/**
 * Convierte el XML firmado de un comprobante FE-CR (v4.4) al array interno
 * que consumen la vista PDF y {@see CostaRicaFeComprobantePdfAggregates}.
 */
final class CostaRicaXmlComprobantePdfMapper
{
    private const ROOTS = [
        'FacturaElectronica',
        'TiqueteElectronico',
        'NotaCreditoElectronica',
        'NotaDebitoElectronica',
        'FacturaElectronicaCompra',
        'FacturaElectronicaExportacion',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromXml(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new InvalidArgumentException('El XML del comprobante está vacío.');
        }

        $doc = XmlLocalNameHelper::loadXml($xml);
        $xp = XmlLocalNameHelper::xpath($doc);
        $root = XmlLocalNameHelper::rootLocalName($doc);

        if ($root === 'MensajeHacienda') {
            throw new InvalidArgumentException('El XML es una respuesta de Hacienda, no el comprobante emitido.');
        }

        if (! in_array($root, self::ROOTS, true)) {
            throw new InvalidArgumentException('Raíz XML no reconocida para representación gráfica FE-CR: '.$root);
        }

        $consecutivo = preg_replace('/\D/', '', (string) (XmlLocalNameHelper::firstText($xp, 'NumeroConsecutivo') ?? ''));
        [$est, $punto, $seq] = self::partesConsecutivo($consecutivo);

        $moneda = strtoupper((string) (XmlLocalNameHelper::firstText($xp, 'CodigoMoneda') ?? 'CRC'));
        $tipoCambio = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'TipoCambio'));

        $resumenNode = self::firstResumenNode($xp);
        $lineItems = self::parseLineItems($xp);
        $payments = self::parsePayments($xp, $resumenNode);

        return [
            'date' => XmlLocalNameHelper::firstText($xp, 'FechaEmision') ?? '',
            'establishment' => $est,
            'emission_point' => $punto,
            'sequential' => $seq,
            'sale_condition' => XmlLocalNameHelper::firstText($xp, 'CondicionVenta', $resumenNode)
                ?? XmlLocalNameHelper::firstText($xp, 'CondicionVenta')
                ?? '01',
            'sale_condition_other' => XmlLocalNameHelper::firstText($xp, 'CondicionVentaOtros', $resumenNode),
            'currency' => [
                'currency_code' => $moneda !== '' ? $moneda : 'CRC',
                'exchange_rate' => $tipoCambio > 0 ? $tipoCambio : 1.0,
            ],
            'issuer' => self::parseParty($xp, 'Emisor'),
            'receiver' => self::parseParty($xp, 'Receptor'),
            'line_items' => $lineItems,
            'payments' => $payments,
            'summary' => self::parseSummary($xp, $resumenNode),
            'referenced_documents' => self::parseReferencias($xp),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function partesConsecutivo(string $digits): array
    {
        if (strlen($digits) >= 20) {
            return [
                substr($digits, 0, 3),
                substr($digits, 3, 5),
                substr($digits, 10, 10),
            ];
        }

        if (strlen($digits) >= 18) {
            return [
                substr($digits, 0, 3),
                substr($digits, 3, 5),
                substr($digits, 8),
            ];
        }

        return ['', '', $digits];
    }

    private static function firstResumenNode(DOMXPath $xp): ?DOMNode
    {
        foreach (['ResumenFactura', 'ResumenTiquete', 'ResumenNota', 'Resumen'] as $name) {
            $nodes = $xp->query("//*[local-name()='{$name}']");
            if ($nodes && $nodes->length > 0) {
                return $nodes->item(0);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseParty(DOMXPath $xp, string $tag): array
    {
        $nodes = $xp->query("//*[local-name()='{$tag}']");
        if (! $nodes || $nodes->length === 0) {
            return [];
        }

        $node = $nodes->item(0);
        $identNodes = XmlLocalNameHelper::allNodes($xp, 'Identificacion', $node);
        $tipoId = '';
        $numero = '';
        if ($identNodes !== []) {
            $ident = $identNodes[0];
            $tipoId = (string) (XmlLocalNameHelper::firstText($xp, 'Tipo', $ident) ?? '');
            $numero = (string) (XmlLocalNameHelper::firstText($xp, 'Numero', $ident) ?? '');
        }

        $nombre = XmlLocalNameHelper::firstText($xp, 'Nombre', $node)
            ?? XmlLocalNameHelper::firstText($xp, 'NombreComercial', $node)
            ?? '';

        $party = [
            'name' => $nombre,
            'trade_name' => XmlLocalNameHelper::firstText($xp, 'NombreComercial', $node),
            'identification_type' => $tipoId,
            'identification_number' => $numero,
            'activity' => XmlLocalNameHelper::firstText($xp, 'CodigoActividad', $node),
        ];

        $correo = XmlLocalNameHelper::firstText($xp, 'CorreoElectronico', $node);
        if ($correo !== null && $correo !== '') {
            $party['email'] = [$correo];
        }

        $telNodes = XmlLocalNameHelper::allNodes($xp, 'Telefono', $node);
        if ($telNodes !== []) {
            $tel = $telNodes[0];
            $party['phone'] = array_filter([
                'country_code' => XmlLocalNameHelper::firstText($xp, 'CodigoPais', $tel),
                'number' => XmlLocalNameHelper::firstText($xp, 'NumTelefono', $tel)
                    ?? trim((string) $tel->textContent),
            ]);
        }

        $ubicNodes = XmlLocalNameHelper::allNodes($xp, 'Ubicacion', $node);
        if ($ubicNodes !== []) {
            $u = $ubicNodes[0];
            $party['location'] = array_filter([
                'province' => ['code' => XmlLocalNameHelper::firstText($xp, 'Provincia', $u)],
                'canton' => XmlLocalNameHelper::firstText($xp, 'Canton', $u),
                'district' => XmlLocalNameHelper::firstText($xp, 'Distrito', $u),
                'address_details' => XmlLocalNameHelper::firstText($xp, 'OtrasSenas', $u),
                'neighborhood' => XmlLocalNameHelper::firstText($xp, 'Barrio', $u),
            ]);
        }

        return array_filter($party, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parseLineItems(DOMXPath $xp): array
    {
        $items = [];
        foreach (XmlLocalNameHelper::allNodes($xp, 'LineaDetalle') as $node) {
            $cantidad = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'Cantidad', $node));
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }

            $precioUnit = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'PrecioUnitario', $node));
            $subTotal = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'SubTotal', $node));
            $montoTotal = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'MontoTotal', $node));
            if ($subTotal <= 0 && $montoTotal > 0) {
                $subTotal = $montoTotal;
            }

            $descuento = 0.0;
            $descRate = null;
            foreach (XmlLocalNameHelper::allNodes($xp, 'Descuento', $node) as $descNode) {
                $mDesc = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'MontoDescuento', $descNode));
                if ($mDesc > 0) {
                    $descuento += $mDesc;
                }
                $pct = XmlLocalNameHelper::firstText($xp, 'PorcentajeDescuento', $descNode);
                if ($pct !== null && $pct !== '') {
                    $descRate = XmlLocalNameHelper::floatValue($pct);
                }
            }
            if ($descuento <= 0 && $montoTotal > $subTotal && $subTotal > 0) {
                $descuento = $montoTotal - $subTotal;
            }

            $totalTax = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'ImpuestoNeto', $node));
            $taxes = self::parseImpuestosLinea($xp, $node);
            if ($totalTax <= 0 && $taxes !== []) {
                $totalTax = array_sum(array_map(static fn (array $t) => (float) ($t['amount'] ?? 0), $taxes));
            }

            $totalLinea = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'MontoTotalLinea', $node));
            if ($totalLinea <= 0) {
                $totalLinea = round($subTotal + $totalTax, 5);
            }

            $line = [
                'cabys_code' => self::extraerCabys($xp, $node),
                'description' => XmlLocalNameHelper::firstText($xp, 'Detalle', $node) ?? '',
                'quantity' => $cantidad,
                'unit_measure' => XmlLocalNameHelper::firstText($xp, 'UnidadMedida', $node) ?? 'Unid',
                'unit_price' => $precioUnit > 0 ? $precioUnit : ($cantidad > 0 ? $subTotal / $cantidad : 0),
                'sub_total' => $subTotal,
                'taxable_base' => $subTotal,
                'total_tax' => $totalTax,
                'total' => $totalLinea,
                'taxes' => $taxes,
            ];

            if ($descuento > 0) {
                $line['discount_amount'] = $descuento;
            }
            if ($descRate !== null) {
                $line['discount_rate'] = $descRate;
            }

            $items[] = $line;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parseImpuestosLinea(DOMXPath $xp, DOMNode $lineaNode): array
    {
        $taxes = [];
        foreach (XmlLocalNameHelper::allNodes($xp, 'Impuesto', $lineaNode) as $impNode) {
            $codigo = XmlLocalNameHelper::firstText($xp, 'Codigo', $impNode) ?? '01';
            if ($codigo !== '01' && $codigo !== '07') {
                continue;
            }

            $tarifa = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'Tarifa', $impNode));
            $monto = XmlLocalNameHelper::floatValue(XmlLocalNameHelper::firstText($xp, 'Monto', $impNode));
            $ivaType = XmlLocalNameHelper::firstText($xp, 'CodigoTarifaIVA', $impNode)
                ?? XmlLocalNameHelper::firstText($xp, 'CodigoTarifa', $impNode)
                ?? '08';

            $taxes[] = [
                'tax_type' => '01',
                'iva_type' => str_pad(preg_replace('/\D/', '', (string) $ivaType), 2, '0', STR_PAD_LEFT),
                'rate' => $tarifa,
                'amount' => $monto,
            ];
        }

        return $taxes;
    }

    private static function extraerCabys(DOMXPath $xp, DOMNode $lineaNode): string
    {
        foreach (XmlLocalNameHelper::allNodes($xp, 'Codigo', $lineaNode) as $codNode) {
            $tipo = XmlLocalNameHelper::firstText($xp, 'Tipo', $codNode);
            $valor = XmlLocalNameHelper::firstText($xp, 'Codigo', $codNode)
                ?? trim((string) $codNode->textContent);
            $digits = preg_replace('/\D/', '', (string) $valor);
            if (strlen($digits) === 13) {
                return $digits;
            }
            if ($tipo === '04' && $digits !== '') {
                return $digits;
            }
        }

        $comercial = XmlLocalNameHelper::firstText($xp, 'CodigoComercial', $lineaNode);

        return preg_replace('/\D/', '', (string) ($comercial ?? ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parsePayments(DOMXPath $xp, ?DOMNode $resumenNode): array
    {
        $ctx = $resumenNode;
        $medio = XmlLocalNameHelper::firstText($xp, 'MedioPago', $ctx)
            ?? XmlLocalNameHelper::firstText($xp, 'MedioPago');
        if ($medio === null || $medio === '') {
            return [];
        }

        $total = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalComprobante', $ctx)
            ?? XmlLocalNameHelper::firstText($xp, 'TotalComprobante')
        );

        return [[
            'payment_method' => $medio,
            'amount' => $total,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseSummary(DOMXPath $xp, ?DOMNode $ctx): array
    {
        $f = static fn (string $name) => XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, $name, $ctx) ?? XmlLocalNameHelper::firstText($xp, $name)
        );

        $totalGravado = $f('TotalGravado');
        $totalExento = $f('TotalExento');
        $totalExonerado = $f('TotalExonerado');
        $totalNoSujeto = $f('TotalNoSujeto') + $f('TotalNoSujetoGravado') + $f('TotalNoSujetoExento');
        $totalVenta = $f('TotalVenta');
        $totalDescuentos = $f('TotalDescuentos');
        $totalVentaNeta = $f('TotalVentaNeta');
        $totalImpuesto = $f('TotalImpuesto') + $f('TotalImpuestoAsumidoEmisorFabrica');
        $totalComprobante = $f('TotalComprobante');
        $totalIvaDevuelto = $f('TotalIVADevuelto');

        if ($totalVentaNeta <= 0 && $totalVenta > 0) {
            $totalVentaNeta = $totalVenta - $totalDescuentos;
        }

        if ($totalComprobante <= 0) {
            $totalComprobante = $totalVentaNeta + $totalImpuesto + $f('TotalOtrosCargos');
        }

        return [
            'total_taxed' => $totalGravado,
            'total_exempt' => $totalExento,
            'total_exonerated' => $totalExonerado,
            'total_non_taxable' => $totalNoSujeto,
            'total_sale' => $totalVenta > 0 ? $totalVenta : ($totalGravado + $totalExento + $totalExonerado),
            'total_discounts' => $totalDescuentos,
            'total_net_sale' => $totalVentaNeta,
            'total_tax' => $totalImpuesto,
            'total_iva_devuelto' => $totalIvaDevuelto,
            'total' => $totalComprobante,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function parseReferencias(DOMXPath $xp): array
    {
        $refs = [];
        foreach (XmlLocalNameHelper::allNodes($xp, 'InformacionReferencia') as $node) {
            $refs[] = array_filter([
                'document_type' => XmlLocalNameHelper::firstText($xp, 'TipoDocIR', $node)
                    ?? XmlLocalNameHelper::firstText($xp, 'TipoDoc', $node),
                'document_number' => XmlLocalNameHelper::firstText($xp, 'Numero', $node)
                    ?? XmlLocalNameHelper::firstText($xp, 'Clave', $node),
                'emission_date' => XmlLocalNameHelper::firstText($xp, 'FechaEmisionIR', $node)
                    ?? XmlLocalNameHelper::firstText($xp, 'FechaEmision', $node),
                'reason' => XmlLocalNameHelper::firstText($xp, 'Razon', $node),
                'referenced_code' => XmlLocalNameHelper::firstText($xp, 'Codigo', $node),
            ]);
        }

        return $refs;
    }
}
