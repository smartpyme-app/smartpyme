<?php

namespace App\Services\Compras\DocumentoImport;

use App\Contracts\Compras\DocumentoImportParserInterface;
use App\DataTransferObjects\Compras\DocumentoImportDto;
use App\Exceptions\Compras\DocumentoImportException;
use App\Services\Compras\DocumentoImport\Support\XmlLocalNameHelper;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use DOMNode;

/**
 * Parser XML comprobantes electrónicos DGT Costa Rica (v4.4).
 */
final class CostaRicaXmlDocumentoParser implements DocumentoImportParserInterface
{
    private const ROOTS_CR = [
        'FacturaElectronica',
        'TiqueteElectronico',
        'NotaCreditoElectronica',
        'NotaDebitoElectronica',
        'FacturaElectronicaCompra',
        'FacturaElectronicaExportacion',
    ];

    public function supports(string $content): bool
    {
        $trim = ltrim($content);
        if ($trim === '' || ($trim[0] !== '<' && ! str_starts_with($trim, '<?xml'))) {
            return false;
        }

        try {
            $doc = XmlLocalNameHelper::loadXml($content);
            $root = XmlLocalNameHelper::rootLocalName($doc);

            return in_array($root, self::ROOTS_CR, true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function parse(string $content): DocumentoImportDto
    {
        $doc = XmlLocalNameHelper::loadXml($content);
        $xp = XmlLocalNameHelper::xpath($doc);
        $root = XmlLocalNameHelper::rootLocalName($doc);

        if ($root === 'MensajeHacienda') {
            throw new DocumentoImportException(
                'Este archivo es la respuesta de Hacienda (acuse), no el comprobante del proveedor. '
                .'Use el XML de la factura electrónica (FacturaElectronica) con el detalle de líneas y otros cargos.'
            );
        }

        if (! in_array($root, self::ROOTS_CR, true)) {
            throw new DocumentoImportException(
                'El XML no corresponde a un comprobante electrónico de Costa Rica reconocido.'
            );
        }

        $tipoCod = DocumentoTipoDocumentoMapper::codigoDesdeRaizXml($root) ?? '01';
        $clave = XmlLocalNameHelper::firstText($xp, 'Clave');
        $consecutivo = XmlLocalNameHelper::firstText($xp, 'NumeroConsecutivo');
        $fechaEmision = XmlLocalNameHelper::fechaSolo(
            XmlLocalNameHelper::firstText($xp, 'FechaEmision')
        );

        $identificacion = [
            'fechaEmision' => $fechaEmision,
            'tipoDocumento' => $tipoCod,
            'clave' => $clave,
            'codigoGeneracion' => $clave,
            'numeroControl' => $consecutivo,
            'consecutivo' => $consecutivo,
        ];

        $emisorNode = $this->firstChildByLocalName($xp, 'Emisor');
        $emisor = $this->parseEmisor($xp, $emisorNode);

        $receptorNode = $this->firstChildByLocalName($xp, 'Receptor');
        $receptor = $this->parseReceptor($xp, $receptorNode);

        $lineas = $this->parseLineas($xp);
        $otrosCargos = $this->parseOtrosCargos($xp);
        $resumen = $this->parseResumen($xp, $otrosCargos);

        $nombre = DocumentoTipoDocumentoMapper::nombre(
            $tipoCod,
            FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA
        );

        return new DocumentoImportDto(
            pais: FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA,
            formatoOrigen: 'xml',
            identificacion: $identificacion,
            emisor: $emisor,
            lineas: $lineas,
            resumen: $resumen,
            documentoOriginal: $content,
            selloRecibido: null,
            tipoDocumentoNombre: $nombre,
            receptor: $receptor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEmisor(\DOMXPath $xp, ?DOMNode $emisorNode): array
    {
        if ($emisorNode === null) {
            return [
                'identificacion' => null,
                'nit' => null,
                'nombre' => '',
                'telefono' => '',
                'correo' => '',
                'direccion' => '',
            ];
        }

        $numero = XmlLocalNameHelper::firstText($xp, 'Numero', $emisorNode);
        $nombre = XmlLocalNameHelper::firstText($xp, 'Nombre', $emisorNode)
            ?? XmlLocalNameHelper::firstText($xp, 'NombreComercial', $emisorNode)
            ?? '';

        $telefono = '';
        $telNodes = XmlLocalNameHelper::allNodes($xp, 'Telefono', $emisorNode);
        if ($telNodes !== []) {
            $telefono = trim((string) $telNodes[0]->textContent);
        }

        $correo = XmlLocalNameHelper::firstText($xp, 'CorreoElectronico', $emisorNode) ?? '';

        $direccion = '';
        $ubicNodes = XmlLocalNameHelper::allNodes($xp, 'Ubicacion', $emisorNode);
        if ($ubicNodes !== []) {
            $u = $ubicNodes[0];
            $partes = array_filter([
                XmlLocalNameHelper::firstText($xp, 'Provincia', $u),
                XmlLocalNameHelper::firstText($xp, 'Canton', $u),
                XmlLocalNameHelper::firstText($xp, 'Distrito', $u),
                XmlLocalNameHelper::firstText($xp, 'OtrasSenas', $u),
            ]);
            $direccion = implode(', ', $partes);
        }

        return [
            'identificacion' => $numero,
            'nit' => $numero,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo,
            'direccion' => $direccion,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseReceptor(\DOMXPath $xp, ?DOMNode $receptorNode): ?array
    {
        if ($receptorNode === null) {
            return null;
        }

        $numero = XmlLocalNameHelper::firstText($xp, 'Numero', $receptorNode);
        $nombre = XmlLocalNameHelper::firstText($xp, 'Nombre', $receptorNode) ?? '';
        $correo = XmlLocalNameHelper::firstText($xp, 'CorreoElectronico', $receptorNode) ?? '';

        return [
            'identificacion' => $numero,
            'nit' => $numero,
            'nombre' => $nombre,
            'correo' => $correo,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseLineas(\DOMXPath $xp): array
    {
        $lineas = [];
        $lineaNodes = XmlLocalNameHelper::allNodes($xp, 'LineaDetalle');

        foreach ($lineaNodes as $idx => $node) {
            $numItem = XmlLocalNameHelper::firstText($xp, 'NumeroLinea', $node)
                ?? (string) ($idx + 1);

            $codigo = $this->extraerCodigoCabys($xp, $node);
            $descripcion = XmlLocalNameHelper::firstText($xp, 'Detalle', $node) ?? '';
            $cantidad = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'Cantidad', $node)
            );
            $precioUnitario = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'PrecioUnitario', $node)
            );
            $montoTotal = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'MontoTotal', $node)
            );
            if ($montoTotal <= 0 && $cantidad > 0 && $precioUnitario > 0) {
                $montoTotal = $cantidad * $precioUnitario;
            }

            $subTotal = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'SubTotal', $node)
            );
            if ($subTotal <= 0) {
                $subTotal = $montoTotal;
            }

            $descuento = $this->parseDescuentoLinea(
                $xp,
                $node,
                $montoTotal,
                $subTotal,
                $cantidad,
                $precioUnitario
            );

            $gravado = 0.0;
            $exento = 0.0;
            $impuestoNodes = XmlLocalNameHelper::allNodes($xp, 'Impuesto', $node);
            foreach ($impuestoNodes as $impNode) {
                $codigoImp = XmlLocalNameHelper::firstText($xp, 'Codigo', $impNode);
                $montoImp = XmlLocalNameHelper::floatValue(
                    XmlLocalNameHelper::firstText($xp, 'Monto', $impNode)
                );
                if ($codigoImp === '01' || $codigoImp === '07') {
                    $gravado += $subTotal;
                } elseif ($montoImp <= 0) {
                    $tarifa = XmlLocalNameHelper::firstText($xp, 'Tarifa', $impNode);
                    if ($tarifa === '0' || $tarifa === '00') {
                        $exento += $subTotal;
                    }
                }
            }

            if ($gravado <= 0 && $exento <= 0) {
                $gravado = $subTotal;
            }

            $lineas[] = [
                'numItem' => (int) $numItem,
                'codigo' => $codigo,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precioUnitario' => $precioUnitario,
                'montoGravado' => $gravado,
                'montoExento' => $exento,
                'montoNoSujeto' => 0.0,
                'descuento' => $descuento,
                'subtotal' => $subTotal,
            ];
        }

        return $lineas;
    }

    /**
     * Suma descuentos del nodo Descuento (v4.4) o infiere MontoTotal − SubTotal.
     */
    private function parseDescuentoLinea(
        \DOMXPath $xp,
        DOMNode $lineaNode,
        float $montoTotal,
        float $subTotal,
        float $cantidad,
        float $precioUnitario
    ): float {
        $descuento = 0.0;
        $nodosDescuento = XmlLocalNameHelper::allNodes($xp, 'Descuento', $lineaNode);

        foreach ($nodosDescuento as $descNode) {
            $monto = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'MontoDescuento', $descNode)
            );
            if ($monto > 0) {
                $descuento += $monto;

                continue;
            }

            $porcentaje = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'PorcentajeDescuento', $descNode)
            );
            if ($porcentaje > 0 && $montoTotal > 0) {
                $descuento += round($montoTotal * ($porcentaje / 100), 5);
            }
        }

        if ($descuento <= 0 && $nodosDescuento === []) {
            $suelto = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'MontoDescuento', $lineaNode)
            );
            if ($suelto > 0) {
                $descuento = $suelto;
            }
        }

        if ($descuento <= 0 && $montoTotal > $subTotal && $subTotal > 0) {
            $descuento = $montoTotal - $subTotal;
        }

        if ($descuento <= 0 && $cantidad > 0 && $precioUnitario > 0 && $subTotal > 0) {
            $bruto = $cantidad * $precioUnitario;
            if ($bruto > $subTotal + 0.00001) {
                $descuento = $bruto - $subTotal;
            }
        }

        return max(0.0, $descuento);
    }

    private function extraerCodigoCabys(\DOMXPath $xp, DOMNode $lineaNode): string
    {
        $cabysDirecto = XmlLocalNameHelper::firstText($xp, 'CodigoCABYS', $lineaNode);
        if ($cabysDirecto !== null && $cabysDirecto !== '') {
            $digits = preg_replace('/\D/', '', $cabysDirecto);
            if (strlen($digits) === 13) {
                return $digits;
            }
        }

        $codigoNodes = XmlLocalNameHelper::allNodes($xp, 'Codigo', $lineaNode);
        foreach ($codigoNodes as $codNode) {
            $tipo = XmlLocalNameHelper::firstText($xp, 'Tipo', $codNode);
            $valor = XmlLocalNameHelper::firstText($xp, 'Codigo', $codNode)
                ?? trim((string) $codNode->textContent);
            if ($valor !== '' && preg_match('/^\d{13}$/', preg_replace('/\D/', '', $valor))) {
                return preg_replace('/\D/', '', $valor);
            }
            if ($tipo === '04' && $valor !== '') {
                return preg_replace('/\D/', '', $valor);
            }
        }

        foreach ($codigoNodes as $codNode) {
            $valor = trim((string) $codNode->textContent);
            if (preg_match('/\d{13}/', $valor, $m)) {
                return $m[0];
            }
        }

        $comercial = XmlLocalNameHelper::firstText($xp, 'CodigoComercial', $lineaNode);

        return $comercial ?? '';
    }

    /**
     * Otros cargos del documento (p. ej. propina legal tipo 06), fuera de ResumenFactura en v4.4.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseOtrosCargos(\DOMXPath $xp): array
    {
        $cargos = [];
        foreach (XmlLocalNameHelper::allNodes($xp, 'OtrosCargos') as $node) {
            $monto = XmlLocalNameHelper::floatValue(
                XmlLocalNameHelper::firstText($xp, 'MontoCargo', $node)
            );
            if ($monto <= 0) {
                continue;
            }

            $tipoOc = XmlLocalNameHelper::firstText($xp, 'TipoDocumentoOC', $node)
                ?? XmlLocalNameHelper::firstText($xp, 'TipoDocumento', $node);
            $detalle = XmlLocalNameHelper::firstText($xp, 'Detalle', $node)
                ?? XmlLocalNameHelper::firstText($xp, 'Nombre', $node);

            if ($detalle === null || trim($detalle) === '') {
                $detalle = $tipoOc === '06'
                    ? 'Servicio o propina'
                    : 'Otros cargos';
            }

            $cargos[] = [
                'tipoDocumentoOc' => $tipoOc,
                'detalle' => trim($detalle),
                'monto' => $monto,
            ];
        }

        return $cargos;
    }

    /**
     * @param  array<int, array<string, mixed>>  $otrosCargos
     * @return array<string, mixed>
     */
    private function parseResumen(\DOMXPath $xp, array $otrosCargos = []): array
    {
        $resumenNode = $this->firstChildByLocalName($xp, 'ResumenFactura');
        $ctx = $resumenNode;

        $totalOtrosCargos = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalOtrosCargos', $ctx)
        );
        if ($totalOtrosCargos <= 0 && $otrosCargos !== []) {
            $totalOtrosCargos = array_sum(array_map(
                static fn (array $c) => (float) ($c['monto'] ?? 0),
                $otrosCargos
            ));
        }

        $totalGravado = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalGravado', $ctx)
        );
        $totalExento = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalExento', $ctx)
        );
        $totalVenta = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalVenta', $ctx)
        );
        $totalImpuesto = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalImpuesto', $ctx)
        );
        $totalComprobante = XmlLocalNameHelper::floatValue(
            XmlLocalNameHelper::firstText($xp, 'TotalComprobante', $ctx)
        );

        $subtotal = $totalVenta > 0 ? $totalVenta : ($totalGravado + $totalExento);

        $tributos = [];
        if ($totalImpuesto > 0) {
            $tributos[] = ['codigo' => '20', 'valor' => $totalImpuesto];
        }

        $condicion = null;
        $condicionTexto = XmlLocalNameHelper::firstText($xp, 'CondicionVenta', $ctx);
        if ($condicionTexto === '02' || strtolower((string) $condicionTexto) === 'credito') {
            $condicion = 2;
        } elseif ($condicionTexto !== null) {
            $condicion = 1;
        }

        $pagos = [];
        $medioPagoNodes = $ctx ? XmlLocalNameHelper::allNodes($xp, 'MedioPago', $ctx) : XmlLocalNameHelper::allNodes($xp, 'MedioPago');
        foreach ($medioPagoNodes as $medioNode) {
            $tipoMedioPago = XmlLocalNameHelper::firstText($xp, 'TipoMedioPago', $medioNode);
            if ($tipoMedioPago !== null && $tipoMedioPago !== '') {
                $pagos[] = ['codigo' => $tipoMedioPago];
            }
        }

        return [
            'subtotal' => $subtotal,
            'subtotalVentas' => $subtotal,
            'totalGravado' => $totalGravado,
            'totalOtrosCargos' => $totalOtrosCargos,
            'otrosCargos' => $otrosCargos,
            'total' => $totalComprobante > 0 ? $totalComprobante : ($subtotal + $totalImpuesto + $totalOtrosCargos),
            'totalPagar' => $totalComprobante > 0 ? $totalComprobante : ($subtotal + $totalImpuesto + $totalOtrosCargos),
            'tributos' => $tributos,
            'ivaRetenido' => 0.0,
            'ivaPercibido' => 0.0,
            'rentaRetenida' => 0.0,
            'condicionOperacion' => $condicion,
            'pagos' => $pagos,
        ];
    }

    private function firstChildByLocalName(\DOMXPath $xp, string $localName): ?DOMNode
    {
        $nodes = $xp->query("//*[local-name()='{$localName}']");

        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }
}
