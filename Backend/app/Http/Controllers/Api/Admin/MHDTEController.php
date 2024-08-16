<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Municipio;
use App\Models\MH\Unidad;

use App\Models\MH\MHCCF;
use App\Models\MH\MHFactura;
use App\Models\MH\MHNotaCredito;
use App\Models\MH\MHAnulacion;
use App\Models\MH\MHContingencia;
use App\Models\MH\MHSujetoExcluido;

use Mail;
use JWTAuth;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Compras\Compra;
use Barryvdh\DomPDF\Facade as PDF;

class MHDTEController extends Controller
{
    

    public function generarDTE(Request $request){
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        if ($venta->nombre_documento == 'Crédito fiscal') {
            $mh = new MHCCF;
            $DTE = $mh->generarDTE($venta);
        }

        if ($venta->nombre_documento == 'Factura') {
            $mh = new MHFactura;
            $DTE = $mh->generarDTE($venta);
        }

        return Response()->json($DTE, 200);
    }

    public function generarDTENotaCredito(Request $request){
        $devolucion = DevolucionVenta::where('id', $request->id)->with('detalles', 'cliente', 'empresa', 'venta')->firstOrFail();

        $mh = new MHNotaCredito;
        $DTE = $mh->generarDTE($devolucion);

        return Response()->json($DTE, 200);
    }

    public function generarDTESujetoExcluido(Request $request){
        $compra = Compra::where('id', $request->id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();
        $mh = new MHSujetoExcluido;
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
        $venta = Venta::where('id', $request->id)->firstOrFail();
        
        $mh = new MHAnulacion;
        $DTEAnular = $mh->generarDTE($venta, $venta->dte);

        return Response()->json($DTEAnular, 200);

    }

    public function generarDTEAnuladoSujetoExcluido(Request $request){
        $copra = Compra::where('id', $request->id)->firstOrFail();
        
        $mh = new MH;
        $DTEAnular = $mh->generarDTEAnuladoSujetoExcluido($copra, $copra->dte);

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
            $v->save();

            
            return Response()->json($DTEEnviado, 200);
        }

        return Response()->json($DTEEnviado, 500);

    }

    public function anularDTESujetoExcluido(Request $request){
        $compra = Compra::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($compra->dte, true);

        $mh = new MH;

        $auth = $mh->auth($compra->empresa);

        if ($auth['status'] == "ERROR") {
            return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
        }

        $mh->compra = $compra;
        
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
            
            $c = Compra::findOrFail($compra->id);
            $c->estado = 'Anulada';
            $c->dte_invalidacion = $DTEAnular;
            $c->save();

            
            return Response()->json($DTEEnviado, 200);
        }

        return Response()->json($DTEEnviado, 500);

    }

    public function generarDTEPDF($id, $tipo){
        
        if ($tipo == '01' || $tipo == '03') {
            $registro = Venta::findOrFail($id);
        }

        if ($tipo == '05') {
            $registro = DevolucionVenta::findOrFail($id);
        }

        if ($tipo == '14') {
            $registro = Compra::findOrFail($id);
        }


        $DTE = $registro->dte;
        // return $DTE;

        $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

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
        if ($DTE['identificacion']['tipoDte'] == '05') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.DTE-CCF', compact('registro', 'DTE'));

        }

        return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');

    }

    public function generarDTEJSON($id, $tipo){

        if ($tipo == '01' || $tipo == '03') {
            $registro = Venta::findOrFail($id);
        }

        if ($tipo == '05') {
            $registro = DevolucionVenta::findOrFail($id);
        }

        if ($tipo == '14') {
            $registro = Compra::findOrFail($id);
        }

        if ($registro->dte_invalidacion)
            $DTE = $registro->dte_invalidacion;
        else
            $DTE = $registro->dte;

        return Response()->json($DTE, 200);

    }


    public function enviarDTE(Request $request){
        $venta = Venta::with('cliente')->where('id', $request->id)->firstOrFail();

        $DTE = $venta->dte;

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($DTE['identificacion']['tipoDte'] == '01') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Factura', compact('venta', 'DTE'));
        }
        elseif ($DTE['identificacion']['tipoDte'] == '14') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('venta', 'DTE'));

        }
        elseif ($DTE['identificacion']['tipoDte'] == '03') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-CCF', compact('venta', 'DTE'));

        }

        $pdfContent = $pdf->output();

        if ($venta->cliente && $venta->cliente->correo) {
            Mail::send('mails.DTE', ['DTE' => $DTE ], function ($m) use ($pdfContent, $DTE, $venta) {
                $m->from(env('MAIL_FROM_ADDRESS'), $DTE['emisor']['nombre'] )
                ->to($venta->cliente->correo, $DTE['receptor']['nombre'])
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
            return Response()->json(['error' => 'No tienen correo'], 500);
        }

    }

    public function enviarDTESujetoExcluido(Request $request){
        $compra = Compra::findOrFail($request->id);

        $DTE = $compra->dte;
        $DTE['receptor']['nombre'] = $DTE['sujetoExcluido']['nombre'];
        
        // Enviar solo en produccion
        if (!JWTAuth::parseToken()->authenticate()->empresa()->pluck('enviar_dte')->first()) {
            $DTE['sujetoExcluido']['correo'] = $DTE['emisor']['correo'];
        }

        $compra->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($DTE['identificacion']['tipoDte'] == '14') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('compra', 'DTE'));

        }

        $pdfContent = $pdf->output();

        if (isset($DTE['sujetoExcluido']['correo'])) {
            Mail::send('mails.DTE', ['DTE' => $DTE ], function ($m) use ($pdfContent, $DTE) {
                $m->from(env('MAIL_FROM_ADDRESS'), $DTE['emisor']['nombre'] )
                ->to($DTE['sujetoExcluido']['correo'], $DTE['sujetoExcluido']['nombre'])
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
            return Response()->json(['error' => 'No tienen correo'], 500);
        }

    }


}
