<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Exceptions\CostaRica\CostaRicaFeEmisionFallidaException;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\FacturacionElectronica\XmlRespuestaHaciendaCr;
use DazzaDev\DgtCr\Client;
use DOMDocument;
use Illuminate\Database\Eloquent\Model;
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

        $sec = $this->secuencialDesdeCorrelativo($venta->correlativo);
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

        $sec = $this->secuencialDesdeCorrelativo($venta->correlativo);
        $data = $this->mapper->buildTicketDocumentData($venta, $venta->empresa, $sec);

        return $this->enviarYPersistirVenta($venta, 'ticket', '04', 'TiqueteElectronico', $data);
    }

    /**
     * FEC 08 — factura electrónica de compra (emisor = empresa, receptor = proveedor).
     *
     * @return array{clave: string, aceptada: bool, detalle_estado: array, compra: Compra}
     */
    public function emitirFacturaElectronicaCompraDesdeCompra(int $compraId): array
    {
        $compra = Compra::query()
            ->with(['detalles.producto', 'proveedor', 'empresa', 'sucursal'])
            ->findOrFail($compraId);

        $empresa = $compra->empresa;
        if ($empresa === null) {
            throw new RuntimeException('La compra no tiene empresa asociada.');
        }
        $this->assertEmpresaCr($empresa);
        if (! $this->esDocumentoCompraElectronicaCr($compra->tipo_documento)) {
            throw new RuntimeException('El tipo de documento debe ser «Factura Electrónica de Compra» (o el nombre histórico «Compra electrónica») para emitir FEC (08).');
        }
        if ($this->compraTieneClaveFeCr($compra)) {
            throw new RuntimeException('La compra ya tiene comprobante electrónico emitido (clave registrada).');
        }

        $sec = $this->secuencialDesdeCorrelativo($compra->referencia);
        $data = $this->mapper->buildFacturaElectronicaCompraDesdeCompra($compra, $empresa, $sec);

        return $this->enviarYPersistirCompra($compra, 'fec', '08', 'FacturaElectronicaCompra', $data);
    }

    /**
     * FEC 08 desde egreso/gasto con líneas de detalle.
     *
     * @return array{clave: string, aceptada: bool, detalle_estado: array, gasto: Gasto}
     */
    public function emitirFacturaElectronicaCompraDesdeGasto(int $gastoId): array
    {
        $gasto = Gasto::query()
            ->with(['detalles', 'proveedor', 'empresa', 'sucursal'])
            ->findOrFail($gastoId);

        $empresa = $gasto->empresa;
        if ($empresa === null) {
            throw new RuntimeException('El gasto no tiene empresa asociada.');
        }
        $this->assertEmpresaCr($empresa);
        if (! $this->esDocumentoCompraElectronicaCr($gasto->tipo_documento)) {
            throw new RuntimeException('El tipo de documento debe ser «Factura Electrónica de Compra» (o el nombre histórico «Compra electrónica») para emitir FEC (08).');
        }
        if ($this->gastoTieneClaveFeCr($gasto)) {
            throw new RuntimeException('El gasto ya tiene comprobante electrónico emitido (clave registrada).');
        }

        $sec = $this->secuencialDesdeCorrelativo($gasto->referencia);
        $data = $this->mapper->buildFacturaElectronicaCompraDesdeGasto($gasto, $empresa, $sec);

        return $this->enviarYPersistirGasto($gasto, 'fec', '08', 'FacturaElectronicaCompra', $data);
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

        $sec = $this->secuencialDesdeCorrelativo($devolucion->correlativo);
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
            'cr' => array_merge([
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ], $this->metadataXmlCr($client)),
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
     * El consecutivo es el {@link Devolucion::correlativo} de la devolución tipo nota de débito ligada a la venta.
     *
     * @return array{clave: string, aceptada: bool, detalle_estado: array, venta: Venta, devolucion?: Devolucion}
     */
    public function emitirNotaDebitoDesdeVenta(int $ventaFacturaId, string $motivo, float $montoLinea): array
    {
        $venta = $this->cargarVenta($ventaFacturaId);
        $empresa = $venta->empresa;
        $this->assertEmpresaCr($empresa);

        if (! $this->ventaFeCrAceptada($venta)) {
            throw new RuntimeException('La venta debe tener factura electrónica aceptada para emitir nota de débito.');
        }

        $devolucionNd = $this->devolucionNotaDebitoParaVenta($venta);
        if ($this->devolucionTieneClaveFeCr($devolucionNd)) {
            throw new RuntimeException('La devolución (nota de débito) ya tiene comprobante electrónico emitido (clave registrada).');
        }

        $dtePrev = is_array($venta->dte) ? $venta->dte : [];
        if (! empty(trim((string) (($dtePrev['cr']['nota_debito']['clave'] ?? ''))))) {
            throw new RuntimeException('Esta venta ya tiene una nota de débito electrónica registrada.');
        }
        if ($montoLinea <= 0) {
            throw new RuntimeException('El monto de la línea debe ser mayor a cero.');
        }

        $sec = $this->secuencialDesdeCorrelativo($devolucionNd->correlativo);
        $saleCond = '01';
        $venta->loadMissing('sucursal');
        $header = $this->mapper->encabezadoDocumento($empresa, (string) $venta->fecha, $sec, $saleCond, $venta->sucursal);

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
        $dte['cr']['nota_debito'] = array_merge([
            'clave' => $clave,
            'aceptada' => true,
            'documento' => $data,
            'envio' => $envio,
            'estado_consulta' => $estado,
        ], $this->metadataXmlCr($client));
        $venta->dte = $dte;
        $venta->save();

        $devolucionNd->codigo_generacion = $clave;
        $devolucionNd->tipo_dte = '02';
        $devolucionNd->sello_mh = $clave;
        $devolucionNd->dte = [
            'pais' => 'CR',
            'tipo' => 'NotaDebitoElectronica',
            'clave' => $clave,
            'documento' => $data,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => '02',
            ],
            'cr' => array_merge([
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ], $this->metadataXmlCr($client)),
        ];
        $devolucionNd->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'venta' => $venta->fresh(),
            'devolucion' => $devolucionNd->fresh(),
        ];
    }

    /**
     * Devolución de venta cuyo documento es nota de débito (SV «Nota de débito», CR «Nota de Débito Electrónica», etc.).
     */
    private function queryDevolucionNotaDebitoPorVenta(Venta $venta): ?Devolucion
    {
        return Devolucion::query()
            ->where('id_venta', $venta->id)
            ->where('enable', true)
            ->whereHas('documento', function ($q): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%nota%'])
                    ->where(function ($q2): void {
                        $q2->whereRaw('LOWER(nombre) LIKE ?', ['%débito%'])
                            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%debito%']);
                    });
            })
            ->orderByDesc('id')
            ->first();
    }

    private function devolucionNotaDebitoParaVenta(Venta $venta): Devolucion
    {
        $d = $this->queryDevolucionNotaDebitoPorVenta($venta);
        if ($d === null) {
            throw new RuntimeException(
                'No hay una devolución de tipo nota de débito registrada para esta venta. Cree la devolución antes de emitir el comprobante electrónico.'
            );
        }

        return $d;
    }

    private function cargarVenta(int $ventaId): Venta
    {
        return Venta::query()
            ->with(['detalles.producto', 'cliente', 'empresa', 'sucursal', 'documento'])
            ->findOrFail($ventaId);
    }

    /**
     * Consecutivo DGT: correlativo en venta/devolución (NC y ND desde devolución); referencia en compra/gasto.
     */
    private function secuencialDesdeCorrelativo(mixed $correlativo): int
    {
        return (int) $correlativo;
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
        // FEC 08 u otros documentos con «compra» no deben emitirse como factura de venta 01.
        if (str_contains($n, 'factura electrónica de compra')
            || str_contains($n, 'factura electronica de compra')
            || str_contains($n, 'compra electrónica')
            || str_contains($n, 'compra electronica')) {
            return false;
        }
        if (str_contains($n, 'orden de compra')) {
            return false;
        }

        return $n === 'factura'
            || str_contains($n, 'factura electrónica')
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

    private function esDocumentoCompraElectronicaCr(?string $nombreDocumento): bool
    {
        $n = mb_strtolower(trim((string) $nombreDocumento), 'UTF-8');

        return str_contains($n, 'compra electrónica')
            || str_contains($n, 'compra electronica')
            || str_contains($n, 'factura electrónica de compra')
            || str_contains($n, 'factura electronica de compra');
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
            'cr' => array_merge([
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ], $this->metadataXmlCr($client)),
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
     * @return array{clave: string, aceptada: bool, detalle_estado: array, compra: Compra}
     */
    private function enviarYPersistirCompra(Compra $compra, string $dgtType, string $tipoDte, string $tipoNombre, array $data): array
    {
        $empresa = $compra->empresa;
        $client = $this->factory->make($empresa);
        $this->configurarClienteEmisorReceptor($client, $data);

        $client->setDocumentType($dgtType);
        $client->setDocumentData($data);

        try {
            $envio = $client->sendDocument();
        } catch (Throwable $e) {
            Log::error('FE CR sendDocument FEC compra', ['compra' => $compra->id, 'tipo' => $dgtType, 'error' => $e->getMessage()]);
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

        $compra->codigo_generacion = $clave;
        $compra->tipo_dte = $tipoDte;
        $compra->sello_mh = $clave;
        $compra->dte = [
            'pais' => 'CR',
            'tipo' => $tipoNombre,
            'clave' => $clave,
            'documento' => $data,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => $tipoDte,
            ],
            'cr' => array_merge([
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ], $this->metadataXmlCr($client)),
        ];
        $compra->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'compra' => $compra->fresh(),
        ];
    }

    /**
     * @return array{clave: string, aceptada: bool, detalle_estado: array, gasto: Gasto}
     */
    private function enviarYPersistirGasto(Gasto $gasto, string $dgtType, string $tipoDte, string $tipoNombre, array $data): array
    {
        $empresa = $gasto->empresa;
        $client = $this->factory->make($empresa);
        $this->configurarClienteEmisorReceptor($client, $data);

        $client->setDocumentType($dgtType);
        $client->setDocumentData($data);

        try {
            $envio = $client->sendDocument();
        } catch (Throwable $e) {
            Log::error('FE CR sendDocument FEC gasto', ['gasto' => $gasto->id, 'tipo' => $dgtType, 'error' => $e->getMessage()]);
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

        $gasto->codigo_generacion = $clave;
        $gasto->tipo_dte = $tipoDte;
        $gasto->sello_mh = $clave;
        $gasto->dte = [
            'pais' => 'CR',
            'tipo' => $tipoNombre,
            'clave' => $clave,
            'documento' => $data,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => $tipoDte,
            ],
            'cr' => array_merge([
                'aceptada' => true,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ], $this->metadataXmlCr($client)),
        ];
        $gasto->save();

        return [
            'clave' => $clave,
            'aceptada' => true,
            'detalle_estado' => $estado,
            'gasto' => $gasto->fresh(),
        ];
    }

    /**
     * Mensaje para error HTTP (misma idea que FE SV: no persistir DTE si Hacienda no aceptó).
     *
     * @param  array<string, mixed>  $estado
     */
    private function mensajeEstadoHaciendaNoAceptado(array $estado): string
    {
        $base = $this->textoBaseMensajeEstadoHaciendaNoAceptado($estado);
        $consejos = $this->consejosMensajesHaciendaCr($estado);

        return $consejos === '' ? $base : $base."\n\n".$consejos;
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function textoBaseMensajeEstadoHaciendaNoAceptado(array $estado): string
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
                } elseif (is_array($m)) {
                    $nested = [];
                    foreach ($m as $cell) {
                        if (is_string($cell) || is_int($cell) || is_float($cell)) {
                            $nested[] = trim((string) $cell);
                        }
                    }
                    if ($nested !== []) {
                        $parts[] = implode(', ', $nested);
                    }
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

    /**
     * Añade orientación cuando Hacienda devuelve códigos frecuentes (-99 consecutivo duplicado, -37 ubicación emisor).
     *
     * @param  array<string, mixed>  $estado
     */
    private function consejosMensajesHaciendaCr(array $estado): string
    {
        $hints = [];
        if ($this->estadoContieneCodigoHacienda($estado, -99)) {
            $hints[] = 'Sugerencia (código -99): el consecutivo enviado ya existe en Hacienda para ese establecimiento / punto de venta / tipo de comprobante. El sistema usa correlativo (venta, devolución) o referencia (compra, gasto): revise que no duplique un comprobante ya aceptado.';
        }
        if ($this->estadoContieneCodigoHacienda($estado, -37)) {
            $hints[] = 'Sugerencia (código -37): provincia, cantón y distrito del emisor deben coincidir con el domicilio fiscal registrado en la DGT. Revise facturacion_fe.emisor_distrito (código INEC de 5 dígitos) o emisor_provincia_manual, emisor_canton_manual y emisor_distrito_manual.';
        }
        if ($this->estadoContieneCodigoHacienda($estado, -111)) {
            $hints[] = 'Sugerencia (código -111): los totales de servicios vs mercancías gravadas deben coincidir con las líneas del XML (UnidadMedida Sp vs Unid y CABYS). En FEC, un CABYS de servicios (p. ej. 83131…) debe ir como servicio; revise tipo de producto y facturacion_fe.cabys_prefijos_servicio si aplica.';
        }

        return implode("\n", $hints);
    }

    /**
     * Detecta si el estado o los mensajes estructurados incluyen un código numérico de Hacienda (p. ej. -99, -37).
     *
     * @param  array<string, mixed>  $estado
     */
    private function estadoContieneCodigoHacienda(array $estado, int $codigo): bool
    {
        if ($this->valorContieneCodigoHacienda($estado['messages'] ?? null, $codigo)) {
            return true;
        }
        $xml = $estado['response_xml'] ?? null;
        if (is_string($xml) && str_contains($xml, (string) $codigo)) {
            return true;
        }

        return $this->valorContieneCodigoHacienda($estado, $codigo);
    }

    private function valorContieneCodigoHacienda(mixed $value, int $codigo): bool
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value === $codigo;
        }
        if (is_string($value)) {
            return preg_match('/\b'.preg_quote((string) $codigo, '/').'\b/', $value) === 1;
        }
        if (! is_array($value)) {
            return false;
        }
        foreach ($value as $k => $v) {
            if (($k === 'codigo' || $k === 'Codigo') && (is_int($v) || is_string($v)) && (int) $v === $codigo) {
                return true;
            }
            if ($this->valorContieneCodigoHacienda($v, $codigo)) {
                return true;
            }
        }

        return false;
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

    private function compraTieneClaveFeCr(Compra $compra): bool
    {
        return trim((string) ($compra->codigo_generacion ?? '')) !== '';
    }

    private function gastoTieneClaveFeCr(Gasto $gasto): bool
    {
        return trim((string) ($gasto->codigo_generacion ?? '')) !== '';
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
     * Para adjuntar en correo al cliente: XML firmado con la clave de Hacienda.
     *
     * @return array<string, string>
     */
    private function metadataXmlCr(Client $client): array
    {
        [$xmlSin, $xmlFirm] = $this->xmlComprobanteDesdeClienteDgt($client);
        $out = [];
        if (is_string($xmlFirm) && $xmlFirm !== '') {
            $out['xml_comprobante_firmado'] = $xmlFirm;
        }
        if (is_string($xmlSin) && $xmlSin !== '') {
            $out['xml_comprobante_sin_firma'] = $xmlSin;
        }

        return $out;
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
     * @return array{venta: Venta, detalle_estado: array<string, mixed>, rechazado?: true}
     */
    public function consultarEstadoVenta(int $ventaId): array
    {
        $venta = $this->cargarVenta($ventaId);

        return $this->consultarEstadoFeCrModelo(
            $venta,
            $venta->empresa,
            'La venta no tiene clave de comprobante; emita primero el comprobante electrónico.',
            'venta'
        );
    }

    /**
     * @return array{devolucion: Devolucion, detalle_estado: array<string, mixed>, rechazado?: true}
     */
    public function consultarEstadoDevolucion(int $devolucionId): array
    {
        $devolucion = Devolucion::query()
            ->with(['empresa'])
            ->findOrFail($devolucionId);
        $empresa = $devolucion->empresa;
        if ($empresa === null) {
            throw new RuntimeException('La devolución no tiene empresa asociada.');
        }

        return $this->consultarEstadoFeCrModelo(
            $devolucion,
            $empresa,
            'La devolución no tiene clave de comprobante; emita primero la nota de crédito electrónica.',
            'devolucion'
        );
    }

    /**
     * @return array{compra: Compra, detalle_estado: array<string, mixed>, rechazado?: true}
     */
    public function consultarEstadoCompra(int $compraId): array
    {
        $compra = Compra::query()
            ->with(['empresa'])
            ->findOrFail($compraId);
        $empresa = $compra->empresa;
        if ($empresa === null) {
            throw new RuntimeException('La compra no tiene empresa asociada.');
        }

        return $this->consultarEstadoFeCrModelo(
            $compra,
            $empresa,
            'La compra no tiene clave de comprobante; emita primero el comprobante electrónico (FEC).',
            'compra'
        );
    }

    /**
     * @return array{gasto: Gasto, detalle_estado: array<string, mixed>, rechazado?: true}
     */
    public function consultarEstadoGasto(int $gastoId): array
    {
        $gasto = Gasto::query()
            ->with(['empresa'])
            ->findOrFail($gastoId);
        $empresa = $gasto->empresa;
        if ($empresa === null) {
            throw new RuntimeException('El egreso no tiene empresa asociada.');
        }

        return $this->consultarEstadoFeCrModelo(
            $gasto,
            $empresa,
            'El egreso no tiene clave de comprobante; emita primero el comprobante electrónico (FEC).',
            'gasto'
        );
    }

    /**
     * Consulta el estado de la nota de débito (02) en Hacienda y actualiza dte.cr.nota_debito.
     *
     * @return array{venta: Venta, detalle_estado: array<string, mixed>, rechazado?: true}
     */
    public function consultarEstadoNotaDebitoVenta(int $ventaId): array
    {
        $venta = $this->cargarVenta($ventaId);
        $this->assertEmpresaCr($venta->empresa);

        $dte = is_array($venta->dte) ? $venta->dte : [];
        $nd = is_array($dte['cr']['nota_debito'] ?? null) ? $dte['cr']['nota_debito'] : [];
        $clave = trim((string) ($nd['clave'] ?? ''));
        if ($clave === '') {
            throw new RuntimeException('No hay nota de débito electrónica registrada para esta venta.');
        }

        $client = $this->factory->make($venta->empresa);
        $estado = $this->checkStatusConReintentosSinPersistirXml($client, $clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);
        $status = strtolower(trim((string) ($estado['status'] ?? '')));

        if ($status === 'rechazado') {
            unset($dte['cr']['nota_debito']);
            $venta->dte = $dte;
            $venta->save();

            $devNd = $this->queryDevolucionNotaDebitoPorVenta($venta);
            if ($devNd instanceof Devolucion && trim((string) ($devNd->codigo_generacion ?? '')) !== '') {
                $devNd->codigo_generacion = null;
                $devNd->tipo_dte = null;
                $devNd->sello_mh = null;
                $devNd->dte = null;
                $devNd->save();
            }

            return [
                'venta' => $venta->fresh(),
                'detalle_estado' => $estado,
                'rechazado' => true,
            ];
        }

        $nd['aceptada'] = $aceptada;
        $nd['estado_consulta'] = $estado;
        $dte['cr']['nota_debito'] = $nd;
        $venta->dte = $dte;
        $venta->save();

        $devNd = $this->queryDevolucionNotaDebitoPorVenta($venta);
        if ($devNd instanceof Devolucion && trim((string) ($devNd->codigo_generacion ?? '')) === $clave) {
            $dteDev = is_array($devNd->dte) ? $devNd->dte : [];
            if (! isset($dteDev['cr']) || ! is_array($dteDev['cr'])) {
                $dteDev['cr'] = [];
            }
            $dteDev['cr']['aceptada'] = $aceptada;
            $dteDev['cr']['estado_consulta'] = $estado;
            $devNd->dte = $dteDev;
            $devNd->save();
        }

        return [
            'venta' => $venta->fresh(),
            'detalle_estado' => $estado,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultarEstadoFeCrModelo(Model $model, $empresa, string $mensajeSinClave, string $responseKey): array
    {
        $this->assertEmpresaCr($empresa);

        $clave = trim((string) ($model->codigo_generacion ?? ''));
        if ($clave === '') {
            throw new RuntimeException($mensajeSinClave);
        }

        $client = $this->factory->make($empresa);
        $estado = $this->checkStatusConReintentosSinPersistirXml($client, $clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);
        $status = strtolower(trim((string) ($estado['status'] ?? '')));

        if ($status === 'rechazado') {
            $model->codigo_generacion = null;
            $model->sello_mh = null;
            $model->tipo_dte = null;
            $model->dte = null;
            $model->save();

            return [
                $responseKey => $model->fresh(),
                'detalle_estado' => $estado,
                'rechazado' => true,
            ];
        }

        $model->sello_mh = $clave;

        $dte = is_array($model->dte) ? $model->dte : [];
        if (($dte['pais'] ?? null) !== 'CR') {
            $dte['pais'] = 'CR';
        }
        $cr = is_array($dte['cr'] ?? null) ? $dte['cr'] : [];
        $cr['aceptada'] = $aceptada;
        $cr['estado_consulta'] = $estado;
        $dte['cr'] = $cr;
        $model->dte = $dte;
        $model->save();

        return [
            $responseKey => $model->fresh(),
            'detalle_estado' => $estado,
        ];
    }
}
