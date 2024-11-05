<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Contabilidad\Partidas\Detalle;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;

use Auth;

class GenerarDocumentosController extends Controller
{

    public function generarDoc($id){

        // Si tiene FE en producción
            $empresa = JWTAuth::parseToken()->authenticate()->empresa()->first();

            if ($empresa->facturacion_electronica && $empresa->fe_ambiente == '01') {
                $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();

                $DTE = $venta->dte;

                if ($DTE) {

                    $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

                    return view('reportes.facturacion.DTE-Ticket', compact('venta', 'DTE'));
                }else{
                    return "El documento no ha sido Emitido";
                }

            }
            
        $venta = Venta::where('id', $id)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        if ($documento->nombre == 'Ticket') {
            $documento = Documento::findOrfail($venta->id_documento);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
        }

//        factura
        if ($documento->nombre == 'Factura') {
            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if(Auth::user()->id_empresa == 38){ //38
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 212){ //212
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.fotopro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 84){ //84
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 75){ //75
                // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 104){ //104
                // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura-Coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 11){ //11
                // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 12){ //12
                // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283.46, 765.35]);
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            } 
            elseif(Auth::user()->id_empresa == 136){ //136 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 609.4488]);
            }
            elseif(Auth::user()->id_empresa == 149){ //149 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 187){//187  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 130){//130  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'landscape');
            }
            elseif(Auth::user()->id_empresa == 177){//177  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 24 ){ //24  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Via-del-Mar', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 174 ){ //174  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Consultora-Raices', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 59 ){ //59  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Smartpyme', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 244 ){ //244 OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-keke', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 210 ){ //210  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Arborea-desg', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 229 ){ //229  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Norbin', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 50 ){ //50  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.RefriAcTotal-Factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 274 ){ //274  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Factura-Flat-Speed-Cars', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            else{
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }


            return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
        }

//        factura sujeto excluido
        if ($documento->nombre == 'Sujeto excluido') {
            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if(Auth::user()->id_empresa == 210){ //210
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Sujeto-Excluido-fact-Arborea-desg', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } else{
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.factura-sujeto-excluido', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }


            return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
        }

//          credito fiscal
        if ($documento->nombre == 'Crédito fiscal') {
            $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            if(Auth::user()->id_empresa == 24){ //24
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.vetvia-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 212){ //212
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-FotoPro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 38){ //38
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.velo-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.hotel-eco-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.kiero-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283, 765]);
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            }
            elseif(Auth::user()->id_empresa == 136){ //136
                // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 297.64, 382.68]);
            }
            elseif(Auth::user()->id_empresa == 158){//158
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Guaca-Mix-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 177){//177  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 187){//187  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait'); 
            } 
            elseif(Auth::user()->id_empresa == 130){//130  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'landscape');
            }
            elseif(Auth::user()->id_empresa == 84){ //84
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.devetsa-cff', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 59){ //59
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.smartpyme-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 210){ //210
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Arborea-Design', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 244){ //210
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-keke', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 229){ //229
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Norbin', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 315){ //315
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Sistema-Impresiones', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 313 ){ //313  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-American-Laundry', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 274 ){ //274  OK V2
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.CCF-Flat-Speed-Cars', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
            else{
                $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.credito', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }
  
            return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
        }


    }

    public function anularDoc(){

        return view('reportes.anulacion');

    }


}
