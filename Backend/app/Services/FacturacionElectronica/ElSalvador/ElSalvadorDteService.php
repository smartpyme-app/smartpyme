<?php

namespace App\Services\FacturacionElectronica\ElSalvador;

use App\Http\Controllers\Api\Admin\MHDTEController;
use App\Http\Requests\MH\ConsultarDTERequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\GenerarContingenciaRequest;
use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use App\Http\Requests\MH\GenerarDTEJSONRequest;
use App\Http\Requests\MH\GenerarDTEPDFRequest;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\MH\MHAnulacion;
use App\Models\MH\MHCCF;
use App\Models\MH\MHContingencia;
use App\Models\MH\MHEventoRetorno;
use App\Models\MH\MHFactura;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use App\Models\MH\MHSujetoExcluidoCompra;
use App\Models\MH\MHSujetoExcluidoGasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Services\MH\ConsultaDteMh;
use App\Support\FacturacionElectronica\CorreoDestinoDte;
use App\Services\MhGovSvGatewayService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Lógica DTE El Salvador (MH). Extraída del controlador para reutilizar y permitir otros países vía puerta de país.
 */
class ElSalvadorDteService
{
    public function __construct(private readonly MhGovSvGatewayService $gateway)
    {
    }

    public function generarDTE(Venta $venta): JsonResponse
    {
        if (! $venta->sucursal()->pluck('cod_estable_mh')->first()) {
            return response()->json(['error' => 'Falta configurar los datos de la sucursal.'], 400);
        }

        if ($venta->nombre_documento == 'Factura de exportación' && ! $venta->cliente) {
            return response()->json(['error' => 'La factura de exportación requiere un cliente.'], 400);
        }

        if ($venta->nombre_documento == 'Factura de exportación'
            && (int) $venta->tipo_item_export !== 2
            && (empty($venta->recinto_fiscal) || empty($venta->regimen))) {
            return response()->json(['error' => 'La exportación de bienes requiere recinto fiscal y régimen de exportación. Si exporta servicios, seleccione Tipo de Ítem: Servicios.'], 400);
        }

        if ($error = $this->faltaDistrito($venta->empresa, $venta->cliente, $venta->nombre_documento == 'Crédito fiscal')) {
            return response()->json(['error' => $error], 400);
        }

        if ($venta->nombre_documento == 'Crédito fiscal') {
            $mh = new MHCCF;
            $DTE = $mh->generarDTE($venta);
        } elseif ($venta->nombre_documento == 'Factura') {
            $mh = new MHFactura;
            $DTE = $mh->generarDTE($venta);
        } elseif ($venta->nombre_documento == 'Factura de exportación') {
            $mh = new MHFacturaExportacion;
            $DTE = $mh->generarDTE($venta);
        } else {
            return response()->json(['error' => 'El tipo de documento no puede emitirse, debe ser uno de los permitidos.'], 400);
        }

        return response()->json($DTE, 200);
    }

    public function generarDTENotaCredito(DevolucionVenta $devolucion): JsonResponse
    {
        if (! $devolucion->venta) {
            return response()->json(['error' => 'La devolución no tiene una venta asignada.'], 400);
        }

        if ($devolucion->nombre_documento == 'Evento de retorno') {
            $original = $devolucion->venta->dte;
            if (! $devolucion->venta->sello_mh || ! in_array($original['identificacion']['tipoDte'] ?? null, ['01', '11'], true)) {
                return response()->json(['error' => 'El evento de retorno solo aplica a facturas o facturas de exportación con sello de Hacienda.'], 400);
            }
            // Plazo del MH: hasta 3 meses desde la fecha de generación del DTE.
            if (\Carbon\Carbon::parse($devolucion->fecha)->gt(\Carbon\Carbon::parse($original['identificacion']['fecEmi'])->addMonthsNoOverflow(3))) {
                return response()->json(['error' => 'El evento de retorno solo puede aplicarse dentro de los 3 meses siguientes a la fecha del documento.'], 400);
            }

            return response()->json((new MHEventoRetorno)->generarDTE($devolucion), 200);
        }

        if ($error = $this->faltaDistrito($devolucion->empresa, $devolucion->cliente, true)) {
            return response()->json(['error' => $error], 400);
        }

        if ($devolucion->nombre_documento == 'Nota de crédito') {
            $mh = new MHNotaCredito;
            $DTE = $mh->generarDTE($devolucion);
        } elseif ($devolucion->nombre_documento == 'Nota de débito') {
            $mh = new MHNotaDebito;
            $DTE = $mh->generarDTE($devolucion);
        } else {
            return response()->json(['error' => 'Tipo de documento no valido, debe de ser Nota de crédito o nota de débito.'], 400);
        }

        return response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluidoGasto(Gasto $gasto): JsonResponse
    {
        if ($error = $this->faltaDistrito($gasto->empresa, $gasto->proveedor, true)) {
            return response()->json(['error' => $error], 400);
        }

        $mh = new MHSujetoExcluidoGasto;
        $DTE = $mh->generarDTE($gasto);

        return response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluidoCompra(Compra $compra): JsonResponse
    {
        if ($error = $this->faltaDistrito($compra->empresa, $compra->proveedor, true)) {
            return response()->json(['error' => $error], 400);
        }

        $mh = new MHSujetoExcluidoCompra;
        $DTE = $mh->generarDTE($compra);

        return response()->json($DTE, 200);
    }

    /** DTE v2+: la dirección exige distrito (CAT-008); se valida antes para no esperar el rechazo del MH. */
    private function faltaDistrito($empresa, $receptor, bool $receptorObligatorio): ?string
    {
        if (empty($empresa?->cod_distrito)) {
            return 'Falta el distrito en la dirección de la empresa; Hacienda lo exige desde la versión 2.0 del DTE.';
        }
        if ($receptorObligatorio && $receptor && empty($receptor->cod_distrito)) {
            return 'Falta el distrito en la dirección de ' . ($receptor->nombre ?? $receptor->nombre_empresa ?? 'el cliente/proveedor') . '; Hacienda lo exige desde la versión 2.0 del DTE.';
        }

        return null;
    }

    public function generarContingencia(GenerarContingenciaRequest $request): JsonResponse
    {
        $ventas = Venta::whereIn('id', [$request->id])
            ->withAccessorRelations()
            ->with('detalles', 'empresa')
            ->get();
        $empresa = $ventas[0]->empresa;

        $DTEs = collect();

        foreach ($ventas as $venta) {
            $DTE = null;
            if ($venta->nombre_documento == 'Crédito fiscal') {
                $mh = new MHCCF;
                $DTE = $mh->generarDTE($venta);
            }

            if ($venta->nombre_documento == 'Factura') {
                $mh = new MHFactura;
                $DTE = $mh->generarDTE($venta);
            }

            if (isset($DTE)) {
                $DTEs->push($DTE);
            }
        }

        if (count($DTEs) == 0) {
            return response()->json(['error' => 'Lo sentimos, no se genero ningún DTE', 'code' => 500], 500);
        }

        $mh = new MHContingencia;
        $response = $mh->generarDTE($empresa, $DTEs, 3);

        return response()->json($response, 200);
    }

    public function generarDTEAnulado(GenerarDTEAnuladoRequest $request): JsonResponse
    {
        if (in_array($request->tipo_dte, ['05', '06', '18'])) {
            $venta = DevolucionVenta::where('id', $request->id)->firstOrFail();
        } else {
            $venta = Venta::where('id', $request->id)->firstOrFail();
        }

        if ($request->has('fecha_anulacion')) {
            $venta->fecha_anulacion = $request->fecha_anulacion;
        }
        if ($request->has('tipo_anulacion')) {
            $venta->tipo_anulacion = $request->tipo_anulacion;
        }
        if ($request->has('motivo_anulacion')) {
            $venta->motivo_anulacion = $request->motivo_anulacion;
        }
        if ($request->has('codigo_generacion_remplazo')) {
            $venta->codigo_generacion_remplazo = $request->codigo_generacion_remplazo;
        }
        if ($request->has('fecha_anulacion') || $request->has('tipo_anulacion') || $request->has('motivo_anulacion') || $request->has('codigo_generacion_remplazo')) {
            $venta->save();
        }

        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($venta, $venta->dte);

        return response()->json($DTEAnular, 200);
    }

    public function generarDTEAnuladoSujetoExcluidoCompra(Compra $compra): JsonResponse
    {
        $compra->loadMissing('proveedor', 'empresa', 'sucursal');
        $dte = $compra->dte;
        $tipoDte = is_array($dte) ? ($dte['identificacion']['tipoDte'] ?? null) : null;

        if ($compra->tipo_documento !== 'Sujeto excluido' && ($compra->tipo_dte ?? '') !== '14') {
            return response()->json([
                'error' => ['Solo se invalida en Hacienda una compra de sujeto excluido emitida por su empresa. Anule la compra en el sistema sin envío a MH.'],
            ], 422);
        }

        if (!$dte || $tipoDte !== '14') {
            return response()->json([
                'error' => ['La compra no tiene un DTE tipo 14 asociado para invalidar en Hacienda.'],
            ], 422);
        }

        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($compra, $dte);

        return response()->json($DTEAnular, 200);
    }

    public function generarDTEAnuladoSujetoExcluidoGasto(Gasto $gasto): JsonResponse
    {
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($gasto, $gasto->dte);

        return response()->json($DTEAnular, 200);
    }

    public function generarTicket(Venta $venta): View
    {
        $venta->loadMissing(['detalles', 'cliente', 'empresa']);

        $DTE = $venta->dte;

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $DTE['identificacion']['ambiente'] . '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        return view('reportes.DTE-Ticket', compact('venta', 'DTE'));
    }

    public function generarDTEPDF(int|string $id, string $tipo, GenerarDTEPDFRequest $request): JsonResponse|Response
    {
        $registro = null;

        if ($tipo === '' || $tipo === 'null') {
            $venta = Venta::find($id);
            $tipo = $venta
                ? ($venta->tipo_dte ?? data_get($venta->dte, 'identificacion.tipoDte') ?? '01')
                : (data_get(DevolucionVenta::find($id), 'dte.identificacion.tipoDte') ?? '05');
        }

        if ($tipo == '01' || $tipo == '03' || $tipo == '11') {
            $registro = Venta::findOrFail($id);
        }

        if (in_array($tipo, ['05', '06', '18'])) {
            $registro = DevolucionVenta::findOrFail($id);
        }

        if ($tipo == '14') {
            if ($request->tipo == 'compra') {
                $registro = Compra::findOrFail($id);
            }
            if ($request->tipo == 'gasto') {
                $registro = Gasto::findOrFail($id);
            }
        }

        if (! $registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        [$DTE, $esAnulado] = MHDTEController::dteParaReporte($registro, $request->input('documento'));

        if ($esAnulado) {
            if (is_string($DTE)) {
                $DTE = json_decode($DTE, true);
            }
            if (empty($DTE) || ! is_array($DTE)) {
                return response()->json(['error' => 'El registro no tiene DTE de invalidación.'], 404);
            }
            MHDTEController::asignarQrConsultaPublica($registro, $DTE);
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');

            return $pdf->stream(($DTE['identificacion']['codigoGeneracion'] ?? 'dte-anulado') . '.pdf');
        }

        if (! $DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $DTE['identificacion']['ambiente'] . '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        $pdf = null;
        $tipoDte = $DTE['identificacion']['tipoDte'] ?? $DTE['identificacion']['tipoEvento'] ?? null;
        if ($tipoDte == '01') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '14') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '03') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '11') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '05') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '06') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Debito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($tipoDte == '18') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Evento-Retorno', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }

        return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');
    }

    public function generarDTEJSON(int|string $id, string $tipo, GenerarDTEJSONRequest $request): JsonResponse
    {
        $registro = null;

        if ($tipo == '01' || $tipo == '03' || $tipo == '11') {
            $registro = Venta::findOrFail($id);
        }

        if (in_array($tipo, ['05', '06', '18'])) {
            $registro = DevolucionVenta::findOrFail($id);
        }

        if ($tipo == '14') {
            if ($request->tipo == 'compra') {
                $registro = Compra::findOrFail($id);
            }
            if ($request->tipo == 'gasto') {
                $registro = Gasto::findOrFail($id);
            }
        }

        if (! $registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        [$DTE, $esAnulado] = MHDTEController::dteParaReporte($registro, $request->input('documento'));

        if ($esAnulado && ! $DTE) {
            return response()->json(['error' => 'El registro no tiene DTE de invalidación.'], 404);
        }

        return response()->json($DTE, 200);
    }

    public function enviarDTE(EnviarDTERequest $request): JsonResponse
    {
        $registro = null;
        $correo = null;

        if ($request->tipo_dte == '01' || $request->tipo_dte == '03' || $request->tipo_dte == '11') {
            $registro = Venta::with('cliente')->where('id', $request->id)->firstOrFail();
            $correo = $registro->cliente ? $registro->cliente->correo : null;
        }

        if (in_array($request->tipo_dte, ['05', '06', '18'])) {
            $registro = DevolucionVenta::with('cliente')->where('id', $request->id)->firstOrFail();
            $correo = $registro->cliente ? $registro->cliente->correo : null;
        }

        if ($request->tipo_dte == '14') {
            if ($request->tipo == 'compra') {
                $registro = Compra::with('proveedor')->where('id', $request->id)->firstOrFail();
                $proveedor = $registro->proveedor()->first();
                $correo = $proveedor ? $proveedor->correo : null;
            }
            if ($request->tipo == 'gasto') {
                $registro = Gasto::with('proveedor')->where('id', $request->id)->firstOrFail();
                $proveedor = $registro->proveedor()->first();
                $correo = $proveedor ? $proveedor->correo : null;
            }
        }

        if (! $registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        $correo = CorreoDestinoDte::resolver(is_string($correo) ? $correo : null, $request->input('correo'));
        if (! is_string($correo) || $correo === '' || ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Registro sin correo electrónico válido.'], 400);
        }

        $DTE = $registro->dte;

        if (! $DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $fromAddress = config('mail.from.address', 'no-reply@smartpyme.app');

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $DTE['identificacion']['ambiente'] . '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
            $nombre = $DTE['documento']['nombre'];

            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdfContent = $pdf->output();

            $fromName = (string) ($DTE['emisor']['nombre'] ?? config('mail.from.name', 'SmartPyme'));

            try {
                Mail::send('mails.DTE-Anulado', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre, $fromAddress, $fromName) {
                    $m->from($fromAddress, $fromName)
                        ->to($correo, $nombre)
                        ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                            'mime' => 'application/pdf',
                        ])
                        ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                            'mime' => 'application/json',
                        ])
                        ->subject('Documento Tributario Electrónico Anulado');
                });
            } catch (\Throwable $e) {
                Log::error('FE SV correo DTE anulado', ['error' => $e->getMessage(), 'id' => $request->id, 'tipo_dte' => $request->tipo_dte]);

                return response()->json(['error' => 'No se pudo enviar el correo. Intente de nuevo o revise la configuración SMTP.'], 500);
            }

            return response()->json($DTE, 200);
        }

        $vistas = [
            '01' => 'DTE-Factura',
            '03' => 'DTE-CCF',
            '05' => 'DTE-Nota-Credito',
            '06' => 'DTE-Nota-Debito',
            '11' => 'DTE-Factura-Exportacion',
            '14' => 'DTE-Sujeto-Excluido',
            '18' => 'DTE-Evento-Retorno',
        ];
        $tipoDte = $DTE['identificacion']['tipoDte'] ?? $DTE['identificacion']['tipoEvento'] ?? null;
        if (! isset($vistas[$tipoDte])) {
            return response()->json(['error' => 'Tipo de documento sin representación gráfica.'], 400);
        }
        $pdfContent = app('dompdf.wrapper')->loadView('reportes.facturacion.' . $vistas[$tipoDte], compact('registro', 'DTE'))->output();

        // Evento de retorno identifica al receptor en "documento"; FSE v1 en "sujetoExcluido".
        $nombre = $DTE['receptor']['nombre'] ?? $DTE['sujetoExcluido']['nombre'] ?? $DTE['documento']['nombre'] ?? '';

        $fromName = (string) ($DTE['emisor']['nombre'] ?? config('mail.from.name', 'SmartPyme'));

        try {
            Mail::send('mails.DTE', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre, $fromAddress, $fromName) {
                $m->from($fromAddress, $fromName)
                    ->to($correo, $nombre)
                    ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                        'mime' => 'application/pdf',
                    ])
                    ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                        'mime' => 'application/json',
                    ])
                    ->subject('Documento Tributario Electrónico');
            });
        } catch (\Throwable $e) {
            Log::error('FE SV correo DTE', ['error' => $e->getMessage(), 'id' => $request->id, 'tipo_dte' => $request->tipo_dte]);

            return response()->json(['error' => 'No se pudo enviar el correo. Intente de nuevo o revise la configuración SMTP.'], 500);
        }

        return response()->json($DTE, 200);
    }

    public function consultarDTE(ConsultarDTERequest $request): JsonResponse
    {
        $tdte = $request->tdte ?? $request->tipoDte;
        $codigoGeneracion = $request->codigoGeneracion;

        if (! $tdte || ! $codigoGeneracion) {
            return response()->json(['error' => 'Faltan tipo DTE y código de generación.'], 422);
        }

        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        $payload = ConsultaDteMh::payload((string) $empresa->nit, (string) $tdte, (string) $codigoGeneracion);

        try {
            $result = $this->gateway->postJson($empresa, '/fesv/recepcion/consultadte', $payload);
        } catch (ConnectionException $e) {
            return response()->json([
                'descripcionMsg' => 'No se pudo conectar con el Ministerio de Hacienda.',
            ], 503);
        }

        return response()->json(ConsultaDteMh::adaptarRespuesta($result['body']), $result['status']);
    }
}
