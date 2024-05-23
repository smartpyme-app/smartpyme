<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\MH\ActividadEconomica;
use App\Models\MH\Departamento;
use App\Models\MH\Municipio;
use App\Models\MH\Unidad;
use App\Models\MH\MH;
use Mail;
use App\Models\Ventas\Venta;
use Barryvdh\DomPDF\Facade as PDF;

class MHController extends Controller
{
    

    public function municipios() {
       
        $municipios = Municipio::orderBy('nombre','asc')->get();
        return Response()->json($municipios, 200);

    }

    public function departamentos() {
       
        $departamentos = Departamento::orderBy('nombre','asc')->get();
        return Response()->json($departamentos, 200);

    }

    public function actividadesEconomicas() {
       
        $actividadesEconomicas = ActividadEconomica::orderBy('nombre','asc')->get();
        return Response()->json($actividadesEconomicas, 200);

    }

    public function unidades() {
       
        $unidades = Unidad::orderBy('nombre','asc')->get();
        return Response()->json($unidades, 200);

    }

    public function generarTicket($id){

        $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        $DTE = json_decode($venta->dte, true);

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=01&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        return view('reportes.DTE-Ticket', compact('venta', 'DTE'));

    }

    public function generarDTE(Request $request){
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        $mh = new MH;
        $DTE = $mh->generarDTE($venta);

        return Response()->json($DTE, 200);
    }

    public function emitirDTE(Request $request){
        $venta = Venta::where('id', $request->id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

        try {
            
            $mh = new MH;

            $auth = $mh->auth($venta->empresa);

            if ($auth['status'] == "ERROR") {
                return response()->json(['error' => [$auth['body']['descripcionMsg']]], 422);
            }

            $DTE = $mh->generarDTE($venta);
            

            if (isset($DTE['status']) == "ERROR") {
                return response()->json($DTE, 500);
            }

            $DTEFirmado = $mh->firmarDTE($DTE);
            
            if (isset($DTEFirmado['status']) == "ERROR") {
                return response()->json(['message' => $DTEFirmado['body']['mensaje']], 500);
            }

            $DTEEnviado = $mh->enviarDTE($auth, $DTEFirmado);

            if (isset($DTEEnviado['estado']) == 'PROCESADO' && isset($DTEEnviado['selloRecibido'])) {
                $DTE['sello'] = $DTEEnviado['selloRecibido'];
                $DTE['firmaElectronica'] = $DTEFirmado['body'];
                
                $v = Venta::findOrFail($venta->id);
                $v->dte = $DTE;
                $v->save();
                
                return Response()->json($v, 200);
            }

            return Response()->json($DTEEnviado, 500);

        } catch (Exception $e) {
            return Response()->json($e, 500);
        }

    }


    public function anularDTE(Request $request){
        $venta = Venta::where('id', $request->id)->firstOrFail();
        $DTE = json_decode($venta->dte, true);

        if (!$venta->dte) {
            $v = Venta::findOrFail($venta->id);
            $v->estado = 'Anulada';
            $v->save();
        }

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

    public function generarDTEPDF($id){
        $venta = Venta::findOrFail($id);

        $DTE = $venta->dte;

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=01&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($DTE['identificacion']['tipoDte'] == '01') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-Factura', compact('venta', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.facturacion.DTE-Factura', compact('venta', 'DTE'));
        }
        elseif ($DTE['identificacion']['tipoDte'] == '03') {
            $pdf = PDF::loadView('reportes.facturacion.DTE-CCF', compact('venta', 'DTE'));
            $pdf->setPaper('US Letter', 'portrait');
            // return view('reportes.facturacion.DTE-CCF', compact('venta', 'DTE'));

        }

        return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');

    }

    public function generarDTEJSON($id){
        $venta = Venta::findOrFail($id);

        $DTE = $venta->dte;

        return Response()->json($DTE, 200);

    }

    public function enviarDTE(Request $request){
        $venta = Venta::findOrFail($request->id);

        $DTE = $venta->dte;

        $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=01&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

        if ($DTE['identificacion']['tipoDte'] == '01') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-Factura', compact('venta', 'DTE'));
        }
        elseif ($DTE['identificacion']['tipoDte'] == '03') {
           $pdf = PDF::loadView('reportes.facturacion.DTE-CCF', compact('venta', 'DTE'));

        }

        $pdfContent = $pdf->output();


        if (isset($DTE['receptor']['correo'])) {
            Mail::send('mails.DTE', ['DTE' => $DTE ], function ($m) use ($pdfContent, $DTE) {
                $m->from(env('MAIL_FROM_ADDRESS'), $DTE['emisor']['nombre'] )
                ->to($DTE['receptor']['correo'], $DTE['receptor']['nombre'])
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
