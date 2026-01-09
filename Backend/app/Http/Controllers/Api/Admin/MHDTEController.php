<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Municipio;
use App\Models\MH\Unidad;

use Illuminate\Support\Facades\Http;
use App\Models\MH\MHCCF;
use App\Models\MH\MHFactura;
use App\Models\MH\MHFacturaExportacion;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHNotaDebito;
use App\Models\MH\MHAnulacion;
use App\Models\MH\MHContingencia;
use App\Models\MH\MHSujetoExcluidoGasto;
use App\Models\MH\MHSujetoExcluidoCompra;

use Mail;
use JWTAuth;
use App\Models\Ventas\Venta;
use App\Models\Compras\Compra;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Compras\Gastos\Gasto;
use Barryvdh\DomPDF\Facade as PDF;

class MHDTEController extends Controller
{
    

    public function generarDTE(Request $request){
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if (!$venta->sucursal()->pluck('cod_estable_mh')->first()) {
            return Response()->json(['error' => 'Falta configurar los datos de la sucursal.'], 400);
        }

        if ($venta->nombre_documento == 'Crédito fiscal') {
            $mh = new MHCCF;
            $DTE = $mh->generarDTE($venta);
        }

        elseif ($venta->nombre_documento == 'Factura') {
            $mh = new MHFactura;
            $DTE = $mh->generarDTE($venta);
        }

        elseif ($venta->nombre_documento == 'Factura de exportación') {
            $mh = new MHFacturaExportacion;
            $DTE = $mh->generarDTE($venta);
        }
        else{
            return Response()->json(['error' => 'El tipo de documento no puede emitirse, debe ser uno de los permitidos.'], 400);
        }

        return Response()->json($DTE, 200);
    }

    public function generarDTENotaCredito(Request $request){
        $devolucion = DevolucionVenta::where('id', $request->id)->with('detalles', 'cliente', 'empresa', 'venta')->firstOrFail();
        
        // if (!$devolucion->venta || !$devolucion->venta->sello_mh) {
        if (!$devolucion->venta) {
            // return response()->json(['error' => 'La venta de este documento no ha sido emitida a hacienda.'], 400);
            return response()->json(['error' => 'La devolución no tiene una venta asignada.'], 400);
        }

        if ($devolucion->nombre_documento == 'Nota de crédito') {
            $mh = new MHNotaCredito;
            $DTE = $mh->generarDTE($devolucion);
        }

        else if ($devolucion->nombre_documento == 'Nota de débito') {
            $mh = new MHNotaDebito;
            $DTE = $mh->generarDTE($devolucion);
        }
        else{
            return response()->json(['error' => 'Tipo de documento no valido, debe de ser Nota de crédito o nota de débito.'], 400);
        }
        return Response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluidoGasto(Request $request){
        $gasto = Gasto::where('id', $request->id)->with('proveedor', 'empresa')->firstOrFail();
        $mh = new MHSujetoExcluidoGasto;
        $DTE = $mh->generarDTE($gasto);

        return Response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluidoCompra(Request $request){
        $compra = Compra::where('id', $request->id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();
        $mh = new MHSujetoExcluidoCompra;
        $DTE = $mh->generarDTE($compra);

        return Response()->json($DTE, 200);
    }

    public function generarContingencia(Request $request){

        $ventas = Venta::whereIn('id', [$request->id])->with('detalles', 'cliente', 'empresa')->get();
        $empresa = $ventas[0]->empresa;

        $DTEs = collect();

        foreach ($ventas as $venta) {
            
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

        if (count($DTEs) == 0)
            return Response()->json(['error' => 'Lo sentimos, no se genero ningún DTE', 'code' => 500], 500);

        $mh = new MHContingencia;
        $response = $mh->generarDTE($empresa, $DTEs, 3);

        return Response()->json($response, 200);
    }

    public function generarDTEAnulado(Request $request){

        if ($request->tipo_dte == '05' || $request->tipo_dte == '06') {
            $venta = DevolucionVenta::where('id', $request->id)->firstOrFail();
        }else{
            $venta = Venta::where('id', $request->id)->firstOrFail();
        }
        
        // Guardar solo la fecha de anulación si viene en el request
        if ($request->has('fecha_anulacion')) {
            $venta->fecha_anulacion = $request->fecha_anulacion;
            $venta->save();
        }
        
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($venta, $venta->dte);

        return Response()->json($DTEAnular, 200);

    }

    public function generarDTEAnuladoSujetoExcluidoCompra(Request $request){
        $compra = Compra::where('id', $request->id)->firstOrFail();
        
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($compra, $compra->dte);

        return Response()->json($DTEAnular, 200);

    }

    public function generarDTEAnuladoSujetoExcluidoGasto(Request $request){
        $gasto = Gasto::where('id', $request->id)->firstOrFail();
        
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($gasto, $gasto->dte);

        return Response()->json($DTEAnular, 200);

    }
    
    public function generarTicket($id){

        $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        $DTE = $venta->dte;

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        return view('reportes.DTE-Ticket', compact('venta', 'DTE'));

    }


    public function anularDTE(Request $request){
        $venta = Venta::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($venta->dte, true);

        $mh = new MH;

        $auth = $mh->auth($venta->empresa);

        if ($auth['status'] == "ERROR") {
            return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
        }

        $mh->venta = $venta;
        
        $DTEAnular = $mh->generarDTEAnulado($DTE);
        // return $DTEAnular;

        if (isset($DTEAnular['status']) == "ERROR") {
            return response()->json($DTEAnular, 500);
        }

        $DTEFirmado = $mh->firmarDTE($DTEAnular);
        
        if ($DTEFirmado['status'] == "ERROR") {
            return response()->json($DTEFirmado, 500);
        }

        // return Response()->json($DTEAnular, 500);
        $DTEEnviado = $mh->anularDTE($auth, $DTE, $DTEFirmado);

        if (isset($DTEEnviado['estado']) == 'PROCESADO' && isset($DTEEnviado['selloRecibido'])) {
            $DTEAnular['sello'] = $DTEEnviado['selloRecibido'];
            $DTEAnular['firmaElectronica'] = $DTEFirmado['body'];
            
            $v = Venta::findOrFail($venta->id);
            $v->estado = 'Anulada';
            $v->dte_invalidacion = $DTEAnular;
            // Guardar solo la fecha de anulación si viene en el request
            if ($request->has('fecha_anulacion')) {
                $v->fecha_anulacion = $request->fecha_anulacion;
            }
            $v->save();

            
            return Response()->json($DTEEnviado, 200);
        }

        return Response()->json($DTEEnviado, 500);

    }

    public function anularDTESujetoExcluido(Request $request){
        $gasto = Gasto::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($gasto->dte, true);

        $mh = new MH;

        $auth = $mh->auth($gasto->empresa);

        if ($auth['status'] == "ERROR") {
            return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
        }

        $mh->gasto = $gasto;
        
        $DTEAnular = $mh->generarDTEAnulado($DTE);
        // return $DTEAnular;

        if (isset($DTEAnular['status']) == "ERROR") {
            return response()->json($DTEAnular, 500);
        }

        $DTEFirmado = $mh->firmarDTE($DTEAnular);
        
        if ($DTEFirmado['status'] == "ERROR") {
            return response()->json($DTEFirmado, 500);
        }

        // return Response()->json($DTEAnular, 500);
        $DTEEnviado = $mh->anularDTE($auth, $DTE, $DTEFirmado);

        if (isset($DTEEnviado['estado']) == 'PROCESADO' && isset($DTEEnviado['selloRecibido'])) {
            $DTEAnular['sello'] = $DTEEnviado['selloRecibido'];
            $DTEAnular['firmaElectronica'] = $DTEFirmado['body'];
            
            $c = Gasto::findOrFail($gasto->id);
            $c->estado = 'Anulada';
            $c->dte_invalidacion = $DTEAnular;
            $c->save();

            
            return Response()->json($DTEEnviado, 200);
        }

        return Response()->json($DTEEnviado, 500);

    }

    public function generarDTEPDF($id, $tipo, Request $request){

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

        if (!$registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        $DTE = $registro->dte;

        if (!$DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        // Si esta anulado
        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
            $pdf = PDF::loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');
        }

        if ($DTE['identificacion']['tipoDte'] == '01') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-Factura', compact('registro', 'DTE'));
        }
        if ($DTE['identificacion']['tipoDte'] == '14') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-Factura', compact('registro', 'DTE'));
        }
        if ($DTE['identificacion']['tipoDte'] == '03') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-CCF', compact('registro', 'DTE'));

        }
        if ($DTE['identificacion']['tipoDte'] == '11') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-CCF', compact('registro', 'DTE'));

        }
        if ($DTE['identificacion']['tipoDte'] == '05') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-CCF', compact('registro', 'DTE'));

        }
        if ($DTE['identificacion']['tipoDte'] == '06') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Nota-Debito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-CCF', compact('registro', 'DTE'));

        }

        return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');

    }

    public function generarDTEJSON($id, $tipo, Request $request){

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

        if (!$registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        if ($registro->dte_invalidacion)
            $DTE = $registro->dte_invalidacion;
        else
            $DTE = $registro->dte;

        return Response()->json($DTE, 200);

    }


    public function enviarDTE(Request $request){
        
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

        if (!$registro) {
            return response()->json(['error' => 'No se encontró el registro correspondiente.'], 404);
        }

        $DTE = $registro->dte;

        if (!$DTE) {
            return response()->json(['error' => 'El registro no tiene DTE.'], 404);
        }

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];


        if ($registro->dte_invalidacion) {
            $DTE = $registro->dte_invalidacion;
            $nombre = $DTE['documento']['nombre'];

            $pdf = PDF::loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
            $pdfContent = $pdf->output();

            if ($correo) {
                Mail::send('mails.DTE-Anulado', ['DTE' => $DTE, 'nombre' => $nombre ], function ($m) use ($pdfContent, $DTE, $correo, $nombre) {
                    $m->from('noreply@smartpyme.sv', $DTE['emisor']['nombre'] )
                    ->to($correo, $nombre)
                    ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                        'mime' => 'application/pdf',
                    ])
                    ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                                'mime' => 'application/json',
                    ])
                    ->subject('Documento Tributario Electrónico Anulado');
                });

                return Response()->json($DTE, 200);
            }
            return Response()->json(['error' => 'El cliente no tienen correo'], 400);
        }
        
        if ($DTE['identificacion']['tipoDte'] == '01') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
        }
        elseif ($DTE['identificacion']['tipoDte'] == '11') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));

        }
        elseif ($DTE['identificacion']['tipoDte'] == '05') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));

        }
        elseif ($DTE['identificacion']['tipoDte'] == '14') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));

        }
        elseif ($DTE['identificacion']['tipoDte'] == '03') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));

        }

        $pdfContent = $pdf->output();

        if($DTE['identificacion']['tipoDte'] == '01' || $DTE['identificacion']['tipoDte'] == '05' || $DTE['identificacion']['tipoDte'] == '03' || $DTE['identificacion']['tipoDte'] == '11'){
            $nombre = $DTE['receptor']['nombre'];
        }
        if($DTE['identificacion']['tipoDte'] == '14'){
            $nombre = $DTE['sujetoExcluido']['nombre'];
        }

        if ($correo) {
            Mail::send('mails.DTE', ['DTE' => $DTE, 'nombre' => $nombre ], function ($m) use ($pdfContent, $DTE, $correo, $nombre) {
                $m->from('noreply@smartpyme.sv', $DTE['emisor']['nombre'] )
                ->to($correo, $nombre)
                ->attachData($pdfContent, $DTE['identificacion']['codigoGeneracion'] . '.pdf', [
                    'mime' => 'application/pdf',
                ])
                ->attachData(json_encode($DTE), $DTE['identificacion']['codigoGeneracion'] . '.json', [
                            'mime' => 'application/json',
                ])
                ->subject('Documento Tributario Electrónico');
            });

            return Response()->json($DTE, 200);
        }else{
            // return Response()->json($DTE, 200);
            return Response()->json(['error' => 'Registro sin correo electrónico'], 400);
        }

    }

    public function consultarDTE(Request $request)
    {
        $response = Http::get('https://admin.factura.gob.sv/prod/consultas/publica/simple/1', [
            'codigoGeneracion' => $request->codigoGeneracion,
            'fechaEmi' => $request->fechaEmi,
            'ambiente' => $request->ambiente,
        ]);

        return $response->json();
    }


}
