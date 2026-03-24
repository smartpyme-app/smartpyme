<?php

namespace App\Services\FacturacionElectronica\ElSalvador;

use App\Http\Requests\MH\AnularDTERequest;
use App\Http\Requests\MH\AnularDTESujetoExcluidoRequest;
use App\Http\Requests\MH\ConsultarDTERequest;
use App\Http\Requests\MH\EnviarDTERequest;
use App\Http\Requests\MH\GenerarContingenciaRequest;
use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use App\Http\Requests\MH\GenerarDTEJSONRequest;
use App\Http\Requests\MH\GenerarDTEPDFRequest;
use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\MH\MH;
use App\Models\MH\MHAnulacion;
use App\Models\MH\MHCCF;
use App\Models\MH\MHContingencia;
use App\Models\MH\MHFactura;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use App\Models\MH\MHSujetoExcluidoCompra;
use App\Models\MH\MHSujetoExcluidoGasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Lógica DTE El Salvador (MH). Extraída del controlador para reutilizar y permitir otros países vía puerta de país.
 */
class ElSalvadorDteService
{
    public function generarDTE(Venta $venta): JsonResponse
    {
        if (! $venta->sucursal()->pluck('cod_estable_mh')->first()) {
            return response()->json(['error' => 'Falta configurar los datos de la sucursal.'], 400);
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
        $mh = new MHSujetoExcluidoGasto;
        $DTE = $mh->generarDTE($gasto);

        return response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluidoCompra(Compra $compra): JsonResponse
    {
        $mh = new MHSujetoExcluidoCompra;
        $DTE = $mh->generarDTE($compra);

        return response()->json($DTE, 200);
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
        if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
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
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($compra, $compra->dte);

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

    public function anularDTE(AnularDTERequest $request): JsonResponse
    {
        $venta = Venta::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($venta->dte, true);

        $mh = new MH;

        $auth = $mh->auth($venta->empresa);

        if ($auth['status'] == 'ERROR') {
            return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
        }

        $mh->venta = $venta;

        $DTEAnular = $mh->generarDTEAnulado($DTE);

        if (isset($DTEAnular['status']) == 'ERROR') {
            return response()->json($DTEAnular, 500);
        }

        $DTEFirmado = $mh->firmarDTE($DTEAnular);

        if ($DTEFirmado['status'] == 'ERROR') {
            return response()->json($DTEFirmado, 500);
        }

        $DTEEnviado = $mh->anularDTE($auth, $DTE, $DTEFirmado);

        if (isset($DTEEnviado['estado']) == 'PROCESADO' && isset($DTEEnviado['selloRecibido'])) {
            $DTEAnular['sello'] = $DTEEnviado['selloRecibido'];
            $DTEAnular['firmaElectronica'] = $DTEFirmado['body'];

            $v = Venta::findOrFail($venta->id);
            $v->estado = 'Anulada';
            $v->dte_invalidacion = $DTEAnular;
            if ($request->has('fecha_anulacion')) {
                $v->fecha_anulacion = $request->fecha_anulacion;
            }
            if ($request->has('tipo_anulacion')) {
                $v->tipo_anulacion = $request->tipo_anulacion;
            }
            if ($request->has('motivo_anulacion')) {
                $v->motivo_anulacion = $request->motivo_anulacion;
            }
            if ($request->has('codigo_generacion_remplazo')) {
                $v->codigo_generacion_remplazo = $request->codigo_generacion_remplazo;
            }
            $v->save();

            return response()->json($DTEEnviado, 200);
        }

        return response()->json($DTEEnviado, 500);
    }

    public function anularDTESujetoExcluido(AnularDTESujetoExcluidoRequest $request): JsonResponse
    {
        $gasto = Gasto::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($gasto->dte, true);

        $mh = new MH;

        $auth = $mh->auth($gasto->empresa);

        if ($auth['status'] == 'ERROR') {
            return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
        }

        $mh->gasto = $gasto;

        $DTEAnular = $mh->generarDTEAnulado($DTE);

        if (isset($DTEAnular['status']) == 'ERROR') {
            return response()->json($DTEAnular, 500);
        }

        $DTEFirmado = $mh->firmarDTE($DTEAnular);

        if ($DTEFirmado['status'] == 'ERROR') {
            return response()->json($DTEFirmado, 500);
        }

        $DTEEnviado = $mh->anularDTE($auth, $DTE, $DTEFirmado);

        if (isset($DTEEnviado['estado']) == 'PROCESADO' && isset($DTEEnviado['selloRecibido'])) {
            $DTEAnular['sello'] = $DTEEnviado['selloRecibido'];
            $DTEAnular['firmaElectronica'] = $DTEFirmado['body'];

            $c = Gasto::findOrFail($gasto->id);
            $c->estado = 'Anulada';
            $c->dte_invalidacion = $DTEAnular;
            $c->save();

            return response()->json($DTEEnviado, 200);
        }

        return response()->json($DTEEnviado, 500);
    }

    public function generarDTEPDF(int|string $id, string $tipo, GenerarDTEPDFRequest $request): JsonResponse|Response
    {
        $registro = null;

        if ($tipo == '01' || $tipo == '03' || $tipo == '11') {
            $registro = Venta::findOrFail($id);
        }

        if ($tipo == '05' || $tipo == '06') {
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

        $DTE = $registro->dte;

        if (! $DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $DTE['identificacion']['ambiente'] . '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');

            return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');
        }

        $pdf = null;
        if ($DTE['identificacion']['tipoDte'] == '01') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($DTE['identificacion']['tipoDte'] == '14') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($DTE['identificacion']['tipoDte'] == '03') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($DTE['identificacion']['tipoDte'] == '11') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($DTE['identificacion']['tipoDte'] == '05') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
        }
        if ($DTE['identificacion']['tipoDte'] == '06') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Debito', compact('registro', 'DTE'));
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

        if ($tipo == '05' || $tipo == '06') {
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

        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
        } else {
            $DTE = $registro->dte;
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

        if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
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

        $DTE = $registro->dte;

        if (! $DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $DTE['identificacion']['ambiente'] . '&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
            $nombre = $DTE['documento']['nombre'];

            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdfContent = $pdf->output();

            if ($correo) {
                Mail::send('mails.DTE-Anulado', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre) {
                    $m->from('noreply@smartpyme.sv', $DTE['emisor']['nombre'])
                        ->to($correo, $nombre)
                        ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                            'mime' => 'application/pdf',
                        ])
                        ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                            'mime' => 'application/json',
                        ])
                        ->subject('Documento Tributario Electrónico Anulado');
                });

                return response()->json($DTE, 200);
            }

            return response()->json(['error' => 'El cliente no tienen correo'], 400);
        }

        $pdf = null;
        if ($DTE['identificacion']['tipoDte'] == '01') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
        } elseif ($DTE['identificacion']['tipoDte'] == '11') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));
        } elseif ($DTE['identificacion']['tipoDte'] == '05') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
        } elseif ($DTE['identificacion']['tipoDte'] == '14') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));
        } elseif ($DTE['identificacion']['tipoDte'] == '03') {
            $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));
        }

        $pdfContent = $pdf->output();

        if ($DTE['identificacion']['tipoDte'] == '01' || $DTE['identificacion']['tipoDte'] == '05' || $DTE['identificacion']['tipoDte'] == '03' || $DTE['identificacion']['tipoDte'] == '11') {
            $nombre = $DTE['receptor']['nombre'];
        }
        if ($DTE['identificacion']['tipoDte'] == '14') {
            $nombre = $DTE['sujetoExcluido']['nombre'];
        }

        if ($correo) {
            Mail::send('mails.DTE', ['DTE' => $DTE, 'nombre' => $nombre], function ($m) use ($pdfContent, $DTE, $correo, $nombre) {
                $m->from('noreply@smartpyme.sv', $DTE['emisor']['nombre'])
                    ->to($correo, $nombre)
                    ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                        'mime' => 'application/pdf',
                    ])
                    ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                        'mime' => 'application/json',
                    ])
                    ->subject('Documento Tributario Electrónico');
            });

            return response()->json($DTE, 200);
        }

        return response()->json(['error' => 'Registro sin correo electrónico'], 400);
    }

    public function consultarDTE(ConsultarDTERequest $request): JsonResponse|array
    {
        $response = Http::get('https://admin.factura.gob.sv/prod/consultas/publica/simple/1', [
            'codigoGeneracion' => $request->codigoGeneracion,
            'fechaEmi' => $request->fechaEmi,
            'ambiente' => $request->ambiente,
        ]);

        return $response->json();
    }
}
