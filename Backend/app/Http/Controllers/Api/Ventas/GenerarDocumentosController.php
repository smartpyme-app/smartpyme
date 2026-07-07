<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;

use App\Models\Ventas\Venta;
use App\Models\Admin\Empresa;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Facades\DB;
// Usamos app('dompdf.wrapper') para evitar errores de Facade en producción

use Auth;

class GenerarDocumentosController extends Controller
{

    public static function empresaUsaImpresionHtml(int $empresaId): bool
    {
        return in_array(
            (int) $empresaId,
            array_map('intval', config('constants.EMPRESAS_IMPRESION_HTML', [])),
            true
        );
    }

    private function responderDocumentoImpresion(string $view, array $data, callable $configurePdf, string $filename)
    {
        if (self::empresaUsaImpresionHtml((int) Auth::user()->id_empresa)) {
            return view($view, $data);
        }

        $data['esPdf'] = true;
        $pdf = app('dompdf.wrapper')->loadView($view, $data);
        $configurePdf($pdf);

        return $pdf->stream($filename);
    }

    public function generarDoc($id){

        // Si tiene FE en producción
            $empresa = JWTAuth::parseToken()->authenticate()->empresa()->first();

            if ($empresa->facturacion_electronica && $empresa->fe_ambiente == '01') {
                $venta = Venta::where('id', $id)->with('detalles', 'cliente', 'empresa')->firstOrFail();
                $documento = Documento::findOrFail($venta->id_documento);

                $DTE = $venta->dte;

                if ($DTE) {

                    $venta->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente='. $DTE['identificacion']['ambiente'] .'&codGen=' . $DTE['identificacion']['codigoGeneracion'] . '&fechaEmi=' . $DTE['identificacion']['fecEmi'];

                    if (
                        isset($empresa->custom_empresa['configuraciones']) &&
                        isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf']) &&
                        $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true
                    ) {
                        $venta->pdf = true;
                        
                        $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Ticket', compact('venta', 'DTE', 'documento', 'empresa'));

                        // Estimar altura:
                           $alto_base = 220; // mm (encabezado, totales, etc.)
                           $alto_por_producto = 30; // mm por línea estimado

                           $total_lineas = $venta->detalles()->count();
                           $notaExtra = ($empresa->mostrarNotaDocumentoImpresion() && $documento->nota)
                               ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                           $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;

                           // Convertir mm a puntos (1mm ≈ 2.83465 pt)
                           $alto_total_pt = $alto_total_mm * 2.83465;
                           $ancho_pt = 80 * 2.83465; // 80mm de ancho

                           $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);

                        return $pdf->stream($DTE['identificacion']['codigoGeneracion'] . '.pdf');

                    }else{
                        $venta->pdf = false;
                        return view('reportes.facturacion.DTE-Ticket', compact('venta', 'DTE', 'documento', 'empresa'));
                    }

                }else{
                    return "El documento no ha sido Emitido";
                }

            }
            
        $venta = Venta::where('id', $id)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        if ($documento->nombre == 'Ticket' || $documento->nombre == 'Recibo') {
            $documento = Documento::findOrfail($venta->id_documento);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $usarTicketAccesoriosHn = (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            );

            if ($usarTicketAccesoriosHn) {
                $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);

                $imprimePdf = isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf'])
                    && $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true;

                if ($imprimePdf) {
                    $venta->pdf = true;
                    $pdf = app('dompdf.wrapper')->loadView(
                        'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                        compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                    );
                    $alto_base = 300;
                    $alto_por_producto = 24;
                    $total_lineas = max(1, $venta->detalles->count());
                    $notaExtra = $documento->nota ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                    $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;
                    $alto_total_pt = $alto_total_mm * 2.83465;
                    $ancho_pt = 80 * 2.83465;
                    $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);
                    return $pdf->stream('ticket-accesorios-hn.pdf');
                }

                $venta->pdf = false;
                return view(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
            }

            if (
                isset($empresa->custom_empresa['configuraciones']) &&
                isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf']) &&
                $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true
            ) {
                $venta->pdf = true;
                
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));

                // Estimar altura:
                   $alto_base = 220; // mm (encabezado, totales, etc.)
                   $alto_por_producto = 7; // mm por línea estimado

                   $total_lineas = $venta->detalles()->count();
                   $notaExtra = ($empresa->mostrarNotaDocumentoImpresion() && $documento->nota)
                       ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                   $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;

                   // Convertir mm a puntos (1mm ≈ 2.83465 pt)
                   $alto_total_pt = $alto_total_mm * 2.83465;
                   $ancho_pt = 80 * 2.83465; // 80mm de ancho

                   $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);

                return $pdf->stream('ticket.pdf');

            }else{
                $venta->pdf = false;
                return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
            }

        }

//        factura
        if ($documento->nombre == 'Factura') {
            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            // Accesorios HN (716) o flag en custom_empresa
            if (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            ) {
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);

                $imprimePdf = isset($empresa->custom_empresa['configuraciones']['ticket_en_pdf'])
                    && $empresa->custom_empresa['configuraciones']['ticket_en_pdf'] == true;

                if ($imprimePdf) {
                    $venta->pdf = true;
                    $pdf = app('dompdf.wrapper')->loadView(
                        'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                        compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                    );
                    $alto_base = 300;
                    $alto_por_producto = 24;
                    $total_lineas = max(1, $venta->detalles->count());
                    $notaExtra = $documento->nota ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                    $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;
                    $alto_total_pt = $alto_total_mm * 2.83465;
                    $ancho_pt = 80 * 2.83465;
                    $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);
                    return $pdf->stream('factura-accesorios-hn.pdf');
                }

                $venta->pdf = false;
                return view(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
            }

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total,2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if(Auth::user()->id_empresa == 38){ //38
                $viewImpresion = 'reportes.facturacion.formatos_empresas.velo';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 212){ //212
                $viewImpresion = 'reportes.facturacion.formatos_empresas.fotopro';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $viewImpresion = 'reportes.facturacion.formatos_empresas.hotel-eco';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 84){ //84
                $viewImpresion = 'reportes.facturacion.formatos_empresas.devetsa';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 75){ //75
                // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Biovet';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 104){ //104
                // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.factura-Coloretes';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 11){ //11
                // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-organika';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 12){ //12
                // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Ayakahuite';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $viewImpresion = 'reportes.facturacion.formatos_empresas.kiero-factura';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 283.46, 765.35]);
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Dentalkey-factura';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 609.45, 467.72]);
            } 
            elseif(Auth::user()->id_empresa == 136){ //136 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Emerson';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 365.669, 609.4488]);
            }
            elseif(Auth::user()->id_empresa == 149){ //149 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Natura';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 187){//187
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Express-Shopping';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 130){//130
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-TecnoGadget';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Legal', 'landscape');
            }
            elseif(Auth::user()->id_empresa == 177){//177
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Credicash';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 24 ){ //24
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Via-del-Mar';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 174 ){ //174
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Consultora-Raices';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 59 ){ //59
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Smartpyme';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 244 ){ //244 OK V2
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-keke';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 210 ){ //210
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Arborea-desg';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 229 ){ //229
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Norbin';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 50 ){ //50
                $viewImpresion = 'reportes.facturacion.formatos_empresas.RefriAcTotal-Factura';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 274 ){ //274
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Flat-Speed-Cars';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 321 ){ //321
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Importaciones-Blanco';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 346 ){ //346
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Vape-Store';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 154 || Auth::user()->id_empresa == 397 || Auth::user()->id_empresa == 398 || Auth::user()->id_empresa == 397 ){ //154
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 396 ){ //154
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Estilos-Salon-SA-CV';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 367 ){ //367
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Clinica';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 250 ){ //250
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Full-Solution';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Legal', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 400 ){ //400
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Zoe-Cosmetics';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 420 ){ //420
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Inversiones-Andre';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 614 ){ //614 Demo SP 2 - Honduras
                $venta->load('detalles.producto');
                $centavos = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Accesorios-Honduras';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 700 ){ //700 Lilian Ohle
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Lilian-Ohle';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 315 ){ //315
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Factura-Sistemas-de-Impresion';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            else{
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.factura';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }


            return $this->responderDocumentoImpresion($viewImpresion, $viewData, $configurePdf, $empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
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
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Sujeto-Excluido-fact-Arborea-desg';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 367 ){ //367
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Sujeto-Excluido-Clinica';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 400 ){ //400
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Sujeto-Zoe-Cosmetics';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            else{
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.factura-sujeto-excluido';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }


            return $this->responderDocumentoImpresion($viewImpresion, $viewData, $configurePdf, $empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
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
                $viewImpresion = 'reportes.facturacion.formatos_empresas.vetvia-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 212){ //212
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-FotoPro';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 38){ //38
                $viewImpresion = 'reportes.facturacion.formatos_empresas.velo-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 62){ //62
                $viewImpresion = 'reportes.facturacion.formatos_empresas.hotel-eco-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 128){ //128
                $viewImpresion = 'reportes.facturacion.formatos_empresas.kiero-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 283, 765]);
            }
            elseif(Auth::user()->id_empresa == 135){ //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Dentalkey-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 609.45, 467.72]);
            }
            elseif(Auth::user()->id_empresa == 136){ //136
                // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $viewImpresion = 'reportes.facturacion.formatos_empresas.destroyesa-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper([0, 0, 297.64, 382.68]);
            }
            elseif(Auth::user()->id_empresa == 158){//158
                $viewImpresion = 'reportes.facturacion.formatos_empresas.Guaca-Mix-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 177){//177
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Credicash';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 187){//187
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Express-Shopping';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            } 
            elseif(Auth::user()->id_empresa == 130){//130
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-TecnoGadget';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Legal', 'landscape');
            }
            elseif(Auth::user()->id_empresa == 84){ //84
                $viewImpresion = 'reportes.facturacion.formatos_empresas.devetsa-cff';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 59){ //59
                $viewImpresion = 'reportes.facturacion.formatos_empresas.smartpyme-ccf';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 210){ //210
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Arborea-Design';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 244){ //210
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-keke';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 229){ //229
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Norbin';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 315){ //315
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Sistema-Impresiones';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 313 ){ //313
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-American-Laundry';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 274 ){ //274
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Flat-Speed-Cars';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 321 ){ //321
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Importaciones-Blanco';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 290 ){ //290
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Grupo-Lievano';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 367 ){ //367
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Clinica';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 250 ){ //250
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Full-Solution';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('Legal', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 400 ){ //400
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Zoe-Cosmetics';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            elseif(Auth::user()->id_empresa == 315 ){ //315
                $viewImpresion = 'reportes.facturacion.formatos_empresas.CCF-Sistemas-de-Impresion';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
            else{
                $viewImpresion = 'reportes.facturacion.formatos_empresas.credito';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
            }
  
            return $this->responderDocumentoImpresion($viewImpresion, $viewData, $configurePdf, $empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
        }

        // Factura de exportación
        if ($documento->nombre == 'Factura comercial') {
            if(Auth::user()->id_empresa == 729){ //729
                $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);
                $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

                $formatter = new NumeroALetras();
                $n = explode(".", number_format($venta->total,2));


                $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
                $centavos = $formatter->toWords($n[1]);
                
                $viewImpresion = 'reportes.facturacion.formatos_empresas.jozano-llc';
                $viewData = compact('venta', 'empresa', 'cliente', 'dolares', 'centavos');
                $configurePdf = fn ($pdf) => $pdf->setPaper('US Letter', 'portrait');
                return $this->responderDocumentoImpresion($viewImpresion, $viewData, $configurePdf, $empresa->nombre . '-factura-exportacion-' . $venta->correlativo . '.pdf');
            }
        }

        return "No hay un formato para este tipo de documento de venta.";

    }

    public function anularDoc(){

        return view('reportes.anulacion');

    }

}
