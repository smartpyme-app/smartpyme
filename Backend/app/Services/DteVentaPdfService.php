<?php

namespace App\Services;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Log;

/**
 * Genera el binario PDF de un DTE de venta, alineado con MHDTEController::generarDTEPDF (solo Venta).
 */
class DteVentaPdfService
{
    public static function renderPdfBinary(Venta $registro): ?string
    {
        try {
            $DTE = $registro->dte;
            if (is_string($DTE)) {
                $DTE = json_decode($DTE, true);
            }
            if (empty($DTE) || !is_array($DTE)) {
                return null;
            }
            if (!isset($DTE['identificacion']['codigoGeneracion'], $DTE['identificacion']['tipoDte'])) {
                return null;
            }

            $ident = $DTE['identificacion'];
            $fecEmi = $ident['fecEmi'] ?? '';
            $registro->qr = 'https://admin.factura.gob.sv/consultaPublica?ambiente=' . $ident['ambiente'] . '&codGen=' . $ident['codigoGeneracion'] . '&fechaEmi=' . $fecEmi;

            if ($registro->dte_invalidacion) {
                $inv = $registro->dte_invalidacion;
                if (is_string($inv)) {
                    $inv = json_decode($inv, true);
                }
                if (empty($inv) || !is_array($inv)) {
                    return null;
                }
                $DTE = $inv;
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Anulado', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');

                return $pdf->output();
            }

            $tipoDte = $DTE['identificacion']['tipoDte'];
            $pdf = null;

            if ($tipoDte == '01') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif ($tipoDte == '14') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Sujeto-Excluido', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif ($tipoDte == '03') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-CCF', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif ($tipoDte == '11') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Factura-Exportacion', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif ($tipoDte == '05') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Credito', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif ($tipoDte == '06') {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.DTE-Nota-Debito', compact('registro', 'DTE'));
                $pdf->setPaper('US Letter', 'portrait');
            }

            if ($pdf === null) {
                Log::warning('DteVentaPdfService: tipo DTE no soportado para venta ' . $registro->id . ' tipo ' . $tipoDte);

                return null;
            }

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::error('DteVentaPdfService venta ' . $registro->id . ': ' . $e->getMessage());

            return null;
        }
    }
}
