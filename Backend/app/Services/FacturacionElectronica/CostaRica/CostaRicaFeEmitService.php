<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Exceptions\CostaRica\CostaRicaFeEmisionFallidaException;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use DazzaDev\DgtCr\Client;
use DOMDocument;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Throwable;

/**
 * Emisión FE Costa Rica: factura 01 (incl. factura de exportación), tiquete 04, nota crédito 03, nota débito 02 (dazza-dev/dgt-cr).
 */
final class CostaRicaFeEmitService
{
    public function __construct(
        private readonly CostaRicaDgtClientFactory $factory,
        private readonly CostaRicaSecuencialService $secuencial,
        private readonly CostaRicaInvoiceFromVentaMapper $mapper,
        private readonly CostaRicaCreditNoteFromDevolucionMapper $creditNoteMapper,
    ) {}

    /**
     * @return array{clave: string, aceptada: bool, detalle_estado: array, venta: Venta}
     */
    public function emitirFacturaDesdeVenta(int $ventaId): array
    {
        $venta = $this->cargarVenta($ventaId);
        $this->assertEmpresaCr($venta->empresa);
        $this->assertDatosExoneracionCrSiAplica($venta);
        if (! $this->esDocumentoFacturaCr($venta->nombre_documento)) {
            throw new RuntimeException('Use emisión de tiquete para documentos tipo Ticket/Tiquete.');
        }
        if ($this->ventaTieneClaveFeCr($venta)) {
            throw new RuntimeException('La venta ya tiene comprobante electrónico emitido (clave registrada).');
        }

        $sec = $this->secuencial->siguienteFactura($venta->empresa);
        $data = $this->mapper->buildDocumentData($venta, $venta->empresa, $sec);

        return $this->enviarYPersistirVenta($venta, 'invoice', '01', 'FacturaElectronica', $data);
    }

    /**
     * @return array{clave: string, aceptada: bool, detalle_estado: array, venta: Venta}
     */
    public function emitirTiqueteDesdeVenta(int $ventaId): array
    {
        $venta = $this->cargarVenta($ventaId);
        $this->assertEmpresaCr($venta->empresa);
        $this->assertDatosExoneracionCrSiAplica($venta);
        if (! $this->esDocumentoTiqueteCr($venta->nombre_documento)) {
            throw new RuntimeException('El documento de la venta debe ser Ticket o Tiquete para comprobante 04.');
        }
        if ($this->ventaTieneClaveFeCr($venta)) {
            throw new RuntimeException('La venta ya tiene comprobante electrónico emitido (clave registrada).');
        }

        $sec = $this->secuencial->siguienteTiquete($venta->empresa);
        $data = $this->mapper->buildTicketDocumentData($venta, $venta->empresa, $sec);

        return $this->enviarYPersistirVenta($venta, 'ticket', '04', 'TiqueteElectronico', $data);
    }

    /**
     * @return array{clave: string, aceptada: bool, detalle_estado: array, devolucion: Devolucion}
     */
    public function emitirNotaCreditoDesdeDevolucion(int $devolucionId): array
    {
        $devolucion = Devolucion::query()
            ->with(['detalles.producto', 'cliente', 'empresa', 'venta.cliente', 'venta.documento'])
            ->findOrFail($devolucionId);

        $empresa = $devolucion->empresa;
        $this->assertEmpresaCr($empresa);

        if ($this->devolucionTieneClaveFeCr($devolucion)) {
            throw new RuntimeException('La devolución ya tiene nota de crédito electrónica emitida (clave registrada).');
        }

        $ventaOrigen = $devolucion->venta;
        if (! $ventaOrigen instanceof Venta) {
            throw new RuntimeException('La devolución no tiene venta origen.');
        }
        if (! $this->ventaFeCrAceptada($ventaOrigen)) {
            throw new RuntimeException('La factura original debe tener comprobante electrónico aceptado en Costa Rica.');
        }

        $sec = $this->secuencial->siguienteNotaCredito($empresa);
        $data = $this->creditNoteMapper->buildDocumentData($devolucion, $empresa, $ventaOrigen, $sec);

        $client = $this->factory->make($empresa);
        $this->configurarClienteEmisorReceptor($client, $data);

        $client->setDocumentType('credit-note');
        $client->setDocumentData($data);

        try {
            $envio = $client->sendDocument();
        } catch (Throwable $e) {
            Log::error('FE CR sendDocument NC', ['devolucion' => $devolucionId, 'error' => $e->getMessage()]);
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                'Error al enviar la nota de crédito a Hacienda: '.$e->getMessage(),
                $data,
                null,
                null,
                $xmlSin,
                $xmlFirm,
                $e
            );
        }

        $clave = $client->getDocumentKey();
        $estado = XmlRespuestaHaciendaCr::normalizarResponseXmlEnEstado(
            $client->checkStatusWithRetry($clave, 3, 2)
        );
        $aceptada = (bool) ($estado['success'] ?? false);

        if (! $aceptada) {
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                $this->mensajeEstadoHaciendaNoAceptado($estado),
                $data,
                $clave,
                $estado,
                $xmlSin,
                $xmlFirm
            );
        }

        $devolucion->codigo_generacion = $clave;
        $devolucion->tipo_dte = '03';
        $devolucion->sello_mh = $clave;
        $devolucion->dte = [
            'pais' => 'CR',
            'tipo' => 'NotaCreditoElectronica',
            'clave' => $clave,
            'documento' => $data,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => '03',
            ],
            'cr' => [
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ],
        ];
        $devolucion->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'devolucion' => $devolucion->fresh(),
        ];
    }

    /**
     * Nota de débito (02) que referencia una factura 01 aceptada (ajuste de montos).
     *
     * @return array{clave: string, aceptada: bool, detalle_estado: array, venta: Venta}
     */
    public function emitirNotaDebitoDesdeVenta(int $ventaFacturaId, string $motivo, float $montoLinea): array
    {
        $venta = $this->cargarVenta($ventaFacturaId);
        $empresa = $venta->empresa;
        $this->assertEmpresaCr($empresa);

        if (! $this->ventaFeCrAceptada($venta)) {
            throw new RuntimeException('La venta debe tener factura electrónica aceptada para emitir nota de débito.');
        }
        if ($montoLinea <= 0) {
            throw new RuntimeException('El monto de la línea debe ser mayor a cero.');
        }

        $sec = $this->secuencial->siguienteNotaDebito($empresa);
        $saleCond = '01';
        $header = $this->mapper->encabezadoDocumento($empresa, (string) $venta->fecha, $sec, $saleCond);

        $claveFactura = (string) $venta->codigo_generacion;
        $fechaFactura = \Carbon\Carbon::parse($venta->fecha)->timezone('America/Costa_Rica')->format('Y-m-d\TH:i:sP');

        $cabys = preg_replace('/\D/', '', (string) $empresa->getCustomConfigValue('facturacion_fe', 'cabys_default', null));
        if (strlen($cabys) !== 13) {
            throw new RuntimeException(
                'Configure custom_empresa.facturacion_fe.cabys_default con un CABYS de 13 dígitos para la línea de nota de débito (el CABYS por producto no aplica a este comprobante).'
            );
        }

        $sub = round($montoLinea / 1.13, 5);
        $iva = round($montoLinea - $sub, 5);

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags($motivo ?: 'Ajuste'), 0, 200),
            'quantity' => 1.0,
            'unit_measure' => 'Unid',
            'unit_price' => $sub,
            'sub_total' => $sub,
            'total_amount' => $sub,
            'taxable_base' => $sub,
            'transaction_type' => '01',
            'total_tax' => $iva,
            'total' => round($montoLinea, 5),
            'taxes' => [[
                'tax_type' => '01',
                'iva_type' => '08',
                'rate' => 13.0,
                'amount' => $iva,
            ]],
        ];

        $summary = [
            'total_taxed_goods' => $sub,
            'total_exempt_goods' => 0.0,
            'total_non_taxable_goods' => 0.0,
            'total_taxed_services' => 0.0,
            'total_exempt_services' => 0.0,
            'total_non_taxable_services' => 0.0,
            'total_taxed' => $sub,
            'total_exempt' => 0.0,
            'total_non_taxable' => 0.0,
            'total_sale' => $sub,
            'total_discounts' => 0.0,
            'total_net_sale' => $sub,
            'total_tax' => $iva,
            'total' => round($montoLinea, 2),
            'taxes' => [[
                'tax_type' => '01',
                'iva_type' => '08',
                'rate' => 13.0,
                'amount' => $iva,
            ]],
        ];

        $data = array_merge($header, [
            'issuer' => $this->mapper->emisorDatos($empresa),
            'receiver' => $this->mapper->receptorDatosVenta($venta, $empresa),
            'line_items' => [$line],
            'payments' => $this->mapper->pagosDesdeMonto(round($montoLinea, 2)),
            'summary' => $summary,
            'referenced_documents' => [[
                'document_type' => '01',
                'document_number' => $claveFactura,
                'emission_date' => $fechaFactura,
                'referenced_code' => '02',
                'reason' => mb_substr(strip_tags($motivo ?: 'Corrección de montos'), 0, 180),
            ]],
        ]);

        // Persistimos en la misma venta (ajuste); segunda clave en dte.cr.nota_debito
        $client = $this->factory->make($empresa);
        $this->configurarClienteEmisorReceptor($client, $data);
        $client->setDocumentType('debit-note');
        $client->setDocumentData($data);

        try {
            $envio = $client->sendDocument();
        } catch (Throwable $e) {
            Log::error('FE CR sendDocument ND', ['venta' => $ventaFacturaId, 'error' => $e->getMessage()]);
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                'Error al enviar la nota de débito a Hacienda: '.$e->getMessage(),
                $data,
                null,
                null,
                $xmlSin,
                $xmlFirm,
                $e
            );
        }

        $clave = $client->getDocumentKey();
        $estado = XmlRespuestaHaciendaCr::normalizarResponseXmlEnEstado(
            $client->checkStatusWithRetry($clave, 3, 2)
        );
        $aceptada = (bool) ($estado['success'] ?? false);

        if (! $aceptada) {
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                $this->mensajeEstadoHaciendaNoAceptado($estado),
                $data,
                $clave,
                $estado,
                $xmlSin,
                $xmlFirm
            );
        }

        $dte = is_array($venta->dte) ? $venta->dte : [];
        $dte['cr']['nota_debito'] = [
            'clave' => $clave,
            'aceptada' => true,
            'documento' => $data,
            'envio' => $envio,
            'estado_consulta' => $estado,
        ];
        $venta->dte = $dte;
        $venta->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'venta' => $venta->fresh(),
        ];
    }

    private function cargarVenta(int $ventaId): Venta
    {
        return Venta::query()
            ->with(['detalles.producto', 'cliente', 'empresa', 'sucursal', 'documento'])
            ->findOrFail($ventaId);
    }

    private function assertEmpresaCr($empresa): void
    {
        if (FacturacionElectronicaCountryResolver::codPais($empresa) !== FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            throw new RuntimeException('La empresa no está configurada como Costa Rica (cod_pais CR).');
        }
        if (! $empresa->facturacion_electronica) {
            throw new RuntimeException('Facturación electrónica desactivada para la empresa.');
        }
    }

    /**
     * Si la venta declara exoneración de IVA (fe_cr_exoneracion.aplica), exige tipo y número de documento.
     */
    private function assertDatosExoneracionCrSiAplica(Venta $venta): void
    {
        $ex = $venta->fe_cr_exoneracion;
        if (! is_array($ex) || empty($ex['aplica'])) {
            return;
        }
        $tipo = trim((string) ($ex['tipo_documento_ex'] ?? ''));
        $num = trim((string) ($ex['numero_documento'] ?? ''));
        if ($tipo === '' || $num === '') {
            throw new RuntimeException(
                'Para exoneración de IVA en Costa Rica indique el tipo de documento y el número de autorización (facturación sin IVA).'
            );
        }
    }

    private function esDocumentoFacturaCr(?string $nombreDocumento): bool
    {
        $n = mb_strtolower(trim((string) $nombreDocumento), 'UTF-8');

        return $n === 'factura'
            || str_contains($n, 'credito fiscal')
            || str_contains($n, 'crédito fiscal')
            || str_contains($n, 'exportación')
            || str_contains($n, 'exportacion');
    }

    private function esDocumentoTiqueteCr(?string $nombreDocumento): bool
    {
        $n = strtolower(trim((string) $nombreDocumento));

        return str_contains($n, 'ticket') || str_contains($n, 'tiquete');
    }

    /**
     * @return array{clave: string, aceptada: bool, detalle_estado: array, venta: Venta}
     */
    private function enviarYPersistirVenta(Venta $venta, string $dgtType, string $tipoDte, string $tipoNombre, array $data): array
    {
        $empresa = $venta->empresa;
        $client = $this->factory->make($empresa);
        $this->configurarClienteEmisorReceptor($client, $data);

        $client->setDocumentType($dgtType);
        $client->setDocumentData($data);

        try {
            $envio = $client->sendDocument();
        } catch (Throwable $e) {
            Log::error('FE CR sendDocument', ['venta' => $venta->id, 'tipo' => $dgtType, 'error' => $e->getMessage()]);
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                'Error al enviar el comprobante a Hacienda: '.$e->getMessage(),
                $data,
                null,
                null,
                $xmlSin,
                $xmlFirm,
                $e
            );
        }

        $clave = $client->getDocumentKey();
        $estado = XmlRespuestaHaciendaCr::normalizarResponseXmlEnEstado(
            $client->checkStatusWithRetry($clave, 3, 2)
        );
        $aceptada = (bool) ($estado['success'] ?? false);

        if (! $aceptada) {
            [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
            throw new CostaRicaFeEmisionFallidaException(
                $this->mensajeEstadoHaciendaNoAceptado($estado),
                $data,
                $clave,
                $estado,
                $xmlSin,
                $xmlFirm
            );
        }

        $venta->codigo_generacion = $clave;
        $venta->tipo_dte = $tipoDte;
        /** Igual criterio práctico que FE SV: sello_mh informado para listados/UI; valor = clave DGT (50 caracteres). */
        $venta->sello_mh = $clave;
        $venta->dte = [
            'pais' => 'CR',
            'tipo' => $tipoNombre,
            'clave' => $clave,
            /** Mismo criterio que El Salvador: JSON completo del comprobante (payload enviado a DGT). */
            'documento' => $data,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => $tipoDte,
            ],
            'cr' => [
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ],
        ];
        $venta->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'venta' => $venta->fresh(),
        ];
    }

    /**
     * Mensaje para error HTTP (misma idea que FE SV: no persistir DTE si Hacienda no aceptó).
     *
     * @param  array<string, mixed>  $estado
     */
    private function mensajeEstadoHaciendaNoAceptado(array $estado): string
    {
        $msg = $estado['messages'] ?? null;
        if (is_string($msg) && trim($msg) !== '') {
            return trim($msg);
        }
        if (is_array($msg)) {
            $parts = [];
            foreach ($msg as $m) {
                if (is_string($m) && trim($m) !== '') {
                    $parts[] = trim($m);
                } elseif ($m !== null && ! is_array($m)) {
                    $parts[] = trim((string) $m);
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        $status = strtolower(trim((string) ($estado['status'] ?? '')));
        if ($status === 'rechazado') {
            return 'El comprobante fue rechazado por Hacienda. Revise los datos y vuelva a intentar la emisión.';
        }

        if ($status === 'recibido' || $status === 'procesando') {
            return 'El comprobante aún no consta como aceptado en Hacienda (en proceso). Espere unos minutos y use «Consultar estado en Hacienda»; no se guardó como emitido.';
        }

        return 'El comprobante no fue aceptado por el Ministerio de Hacienda. No se registró como emitido.';
    }

    private function configurarClienteEmisorReceptor(\DazzaDev\DgtCr\Client $client, array $data): void
    {
        $client->setIssuer([
            'identification_type' => $data['issuer']['identification_type'],
            'identification_number' => $this->soloDigitos($data['issuer']['identification_number']),
        ]);
        $client->setReceiver([
            'identification_type' => $data['receiver']['identification_type'],
            'identification_number' => $this->soloDigitos($data['receiver']['identification_number']),
        ]);
    }

    private function ventaFeCrAceptada(Venta $venta): bool
    {
        $dte = $venta->dte;

        return is_array($dte)
            && ($dte['pais'] ?? null) === 'CR'
            && ! empty($dte['cr']['aceptada']);
    }

    private function devolucionFeCrAceptada(Devolucion $devolucion): bool
    {
        $dte = $devolucion->dte;

        return is_array($dte)
            && ($dte['pais'] ?? null) === 'CR'
            && ! empty($dte['cr']['aceptada']);
    }

    private function ventaTieneClaveFeCr(Venta $venta): bool
    {
        return trim((string) ($venta->codigo_generacion ?? '')) !== '';
    }

    private function devolucionTieneClaveFeCr(Devolucion $devolucion): bool
    {
        return trim((string) ($devolucion->codigo_generacion ?? '')) !== '';
    }

    private function soloDigitos(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }

    /**
     * XML en español (XSD DGT) generado por dgt-xml-generator: sin firma y, si aplica, firmado (p. ej. tras fallo de red).
     *
     * @return array{0: ?string, 1: ?string} [sin_firma, firmado]
     */
    private function xmlComprobanteDesdeClienteDgt(Client $client): array
    {
        return [$this->xmlSinFirmaDesdeClienteDgt($client), $this->xmlFirmadoDesdeClienteDgt($client)];
    }

    private function xmlSinFirmaDesdeClienteDgt(Client $client): ?string
    {
        try {
            $ref = new ReflectionClass($client);
            if (! $ref->hasProperty('document')) {
                return null;
            }
            $prop = $ref->getProperty('document');
            $prop->setAccessible(true);
            $document = $prop->getValue($client);
            if (! is_object($document) || ! method_exists($document, 'getDocumentXml')) {
                return null;
            }
            $dom = $document->getDocumentXml();
            if (! $dom instanceof DOMDocument) {
                return null;
            }
            $dom->formatOutput = true;
            $xml = $dom->saveXML();

            return $xml !== false ? $xml : null;
        } catch (ReflectionException|Throwable) {
            return null;
        }
    }

    private function xmlFirmadoDesdeClienteDgt(Client $client): ?string
    {
        try {
            $ref = new ReflectionClass($client);
            if (! $ref->hasProperty('signedDocument')) {
                return null;
            }
            $prop = $ref->getProperty('signedDocument');
            $prop->setAccessible(true);
            if (! $prop->isInitialized($client)) {
                return null;
            }
            $signed = $prop->getValue($client);

            return is_string($signed) && $signed !== '' ? $signed : null;
        } catch (ReflectionException|Throwable) {
            return null;
        }
    }

    /**
     * Igual que {@see Client::checkStatusWithRetry} pero sin guardar XML en disco (evita Document + getDocumentFileName).
     *
     * @return array<string, mixed>
     */
    private function checkStatusConReintentosSinPersistirXml(Client $client, string $documentKey, int $maxAttempts = 3, int $delaySeconds = 2): array
    {
        $claveConsulta = $documentKey;
        $lastStatus = null;
        $lastDocumentKey = null;
        $lastDate = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $documentStatus = $client->checkStatus($claveConsulta);

            $status = $documentStatus['ind-estado'] ?? null;
            $claveRespuesta = $documentStatus['clave'] ?? null;
            $date = $documentStatus['fecha'] ?? null;

            if ($status === 'rechazado' || $status === 'aceptado') {
                $responseXml = isset($documentStatus['respuesta-xml'])
                    ? base64_decode((string) $documentStatus['respuesta-xml'], true)
                    : null;
                if ($responseXml === false) {
                    $responseXml = null;
                }

                $messages = null;
                if (is_string($responseXml) && $responseXml !== '') {
                    try {
                        $messages = $client->extractMessages($responseXml);
                    } catch (Throwable) {
                        $messages = null;
                    }
                }

                return XmlRespuestaHaciendaCr::normalizarResponseXmlEnEstado([
                    'success' => $status === 'aceptado',
                    'status' => $status,
                    'document_key' => $claveRespuesta,
                    'date' => $date,
                    'response_xml' => $responseXml,
                    'messages' => $messages,
                ]);
            }

            if ($status !== 'recibido' && $status !== 'procesando') {
                return XmlRespuestaHaciendaCr::normalizarResponseXmlEnEstado([
                    'success' => false,
                    'status' => $status ?? '',
                    'document_key' => $claveRespuesta,
                    'date' => $date,
                    'response_xml' => null,
                    'messages' => null,
                ]);
            }

            $lastStatus = $status;
            $lastDocumentKey = $claveRespuesta;
            $lastDate = $date;

            if ($attempt < $maxAttempts - 1) {
                sleep($delaySeconds);
            }
        }

        return [
            'success' => false,
            'status' => $lastStatus ?? '',
            'document_key' => $lastDocumentKey,
            'date' => $lastDate,
            'response_xml' => null,
            'messages' => null,
        ];
    }

    /**
     * Consulta el estado del comprobante en Hacienda y actualiza la venta (dte.cr; sello_mh = clave).
     *
     * @return array{venta: Venta, detalle_estado: array<string, mixed>}
     */
    public function consultarEstadoVenta(int $ventaId): array
    {
        $venta = $this->cargarVenta($ventaId);
        $this->assertEmpresaCr($venta->empresa);

        $clave = trim((string) ($venta->codigo_generacion ?? ''));
        if ($clave === '') {
            throw new RuntimeException('La venta no tiene clave de comprobante; emita primero el comprobante electrónico.');
        }

        $client = $this->factory->make($venta->empresa);
        $estado = $this->checkStatusConReintentosSinPersistirXml($client, $clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);
        $status = strtolower(trim((string) ($estado['status'] ?? '')));

        if ($status === 'rechazado') {
            $venta->codigo_generacion = null;
            $venta->sello_mh = null;
            $venta->tipo_dte = null;
            $venta->dte = null;
            $venta->save();

            return [
                'venta' => $venta->fresh(),
                'detalle_estado' => $estado,
                'rechazado' => true,
            ];
        }

        $venta->sello_mh = $clave;

        $dte = is_array($venta->dte) ? $venta->dte : [];
        if (($dte['pais'] ?? null) !== 'CR') {
            $dte['pais'] = 'CR';
        }
        $cr = is_array($dte['cr'] ?? null) ? $dte['cr'] : [];
        $cr['aceptada'] = $aceptada;
        $cr['estado_consulta'] = $estado;
        $dte['cr'] = $cr;
        $venta->dte = $dte;
        $venta->save();

        return [
            'venta' => $venta->fresh(),
            'detalle_estado' => $estado,
        ];
    }
}
