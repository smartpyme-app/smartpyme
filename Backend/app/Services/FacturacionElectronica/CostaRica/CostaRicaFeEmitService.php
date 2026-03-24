<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Emisión FE Costa Rica: factura 01, tiquete 04, nota crédito 03, nota débito 02 (dazza-dev/dgt-cr).
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
        if (! $this->esDocumentoFacturaCr($venta->nombre_documento)) {
            throw new RuntimeException('Use emisión de tiquete para documentos tipo Ticket/Tiquete.');
        }
        if ($this->ventaFeCrAceptada($venta)) {
            throw new RuntimeException('La venta ya tiene un comprobante electrónico aceptado.');
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
        if (! $this->esDocumentoTiqueteCr($venta->nombre_documento)) {
            throw new RuntimeException('El documento de la venta debe ser Ticket o Tiquete para comprobante 04.');
        }
        if ($this->ventaFeCrAceptada($venta)) {
            throw new RuntimeException('La venta ya tiene un comprobante electrónico aceptado.');
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

        if ($this->devolucionFeCrAceptada($devolucion)) {
            throw new RuntimeException('La devolución ya tiene nota de crédito electrónica aceptada.');
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
            throw $e;
        }

        $clave = $client->getDocumentKey();
        $estado = $client->checkStatusWithRetry($clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);

        $devolucion->codigo_generacion = $clave;
        $devolucion->tipo_dte = '03';
        $devolucion->sello_mh = $aceptada ? 'CR-ACEPTADA' : null;
        $devolucion->dte = [
            'pais' => 'CR',
            'tipo' => 'NotaCreditoElectronica',
            'clave' => $clave,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => '03',
            ],
            'cr' => [
                'aceptada' => $aceptada,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ],
        ];
        $devolucion->save();

        return [
            'clave' => $clave,
            'aceptada' => $aceptada,
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

        $cabysDefault = $empresa->getCustomConfigValue('facturacion_fe', 'cabys_default', null);
        $cabys = preg_replace('/\D/', '', (string) $cabysDefault);
        if (strlen($cabys) !== 13) {
            throw new RuntimeException('Configure custom_empresa.facturacion_fe.cabys_default (13 dígitos) para notas de débito.');
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
                'iva_type' => ['code' => '08', 'name' => 'Tarifa general 13%'],
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
                'iva_type' => ['code' => '08', 'name' => 'Tarifa general 13%'],
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
            throw $e;
        }

        $clave = $client->getDocumentKey();
        $estado = $client->checkStatusWithRetry($clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);

        $dte = is_array($venta->dte) ? $venta->dte : [];
        $dte['cr']['nota_debito'] = [
            'clave' => $clave,
            'aceptada' => $aceptada,
            'envio' => $envio,
            'estado_consulta' => $estado,
        ];
        $venta->dte = $dte;
        $venta->save();

        return [
            'clave' => $clave,
            'aceptada' => $aceptada,
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

    private function esDocumentoFacturaCr(?string $nombreDocumento): bool
    {
        $n = strtolower(trim((string) $nombreDocumento));

        return $n === 'factura'
            || str_contains($n, 'credito fiscal')
            || str_contains($n, 'crédito fiscal');
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
            throw $e;
        }

        $clave = $client->getDocumentKey();
        $estado = $client->checkStatusWithRetry($clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);

        $venta->codigo_generacion = $clave;
        $venta->tipo_dte = $tipoDte;
        $venta->sello_mh = $aceptada ? 'CR-ACEPTADA' : null;
        $venta->dte = [
            'pais' => 'CR',
            'tipo' => $tipoNombre,
            'clave' => $clave,
            'identificacion' => [
                'codigoGeneracion' => $clave,
                'tipoDte' => $tipoDte,
            ],
            'cr' => [
                'aceptada' => $aceptada,
                'envio' => $envio,
                'estado_consulta' => $estado,
            ],
        ];
        $venta->save();

        return [
            'clave' => $clave,
            'aceptada' => $aceptada,
            'detalle_estado' => $estado,
            'venta' => $venta->fresh(),
        ];
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

    private function soloDigitos(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }

    /**
     * Consulta el estado del comprobante en Hacienda y actualiza la venta (dte.cr, sello_mh si aceptado).
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
        $estado = $client->checkStatusWithRetry($clave, 3, 2);
        $aceptada = (bool) ($estado['success'] ?? false);

        if ($aceptada) {
            $venta->sello_mh = 'CR-ACEPTADA';
        }

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
