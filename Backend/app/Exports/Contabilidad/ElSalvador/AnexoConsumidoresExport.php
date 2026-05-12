<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;

class AnexoConsumidoresExport implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     */
    private function tieneFacturacionElectronica(): bool
    {
        $empresa = Auth::user()->empresa()->first();
        return $empresa && $empresa->facturacion_electronica === true;
    }

    /**
     * Obtiene la clase de documento (DTE o Impreso)
     */
    private function obtenerClaseDocumento($venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh) {
            return '4'; // DTE
        }
        return '1'; // Impreso
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->whereHas('documento', function($q) {
                            $q->where('nombre', 'Factura')->orWhere('nombre', 'Factura de exportación');
                        })
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();
        return $ventas;
        
    }

    public function map($venta): array{
            setlocale(LC_NUMERIC, 'C');

            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            $tipo = '01'; //CF
            $esFacturaExportacion = $documento && strtolower(trim($documento->nombre ?? '')) === 'factura de exportación';

            if ($esFacturaExportacion) {
                $tipo = '11';
            }

            // Para facturas de exportación, no asignar valores a gravada/exenta
            $cuentaTerceros = (float) ($venta->cuenta_a_terceros ?? 0);
            $totalPropio = max(0, (float) $venta->total - $cuentaTerceros);

            if ($esFacturaExportacion) {
                $venta->exenta = 0;
                $venta->gravada = 0;
            } elseif ($venta->iva > 0) {
                $venta->exenta = 0;
                $venta->gravada = $totalPropio;
            } else {
                $venta->gravada = 0;
                $venta->exenta = $totalPropio;
            }

           // Según guía de Hacienda:
           // Para documentos IMPRESOS (sin FE): F y G = correlativo, H e I = correlativo
           // Para documentos DTE (con FE): F y G = código generación, H e I = vacío o código generación
           $tieneFE = $this->tieneFacturacionElectronica() && $venta->sello_mh;
           $codigoGeneracion = $tieneFE && isset($venta->dte['identificacion']['codigoGeneracion']) 
               ? str_replace('-', '', $venta->dte['identificacion']['codigoGeneracion']) 
               : '';
           $correlativo = trim($venta->correlativo);
           
           $fields = [
                \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), //A Fecha
                $this->obtenerClaseDocumento($venta), //B Clase DTE o Impreso,
                '01', //C Tipo
                $tieneFE ? str_replace('-', '', $venta->dte['identificacion']['numeroControl'] ?? '') : '', //D Resolucion (vacío si impreso)
                $tieneFE ? ($venta->dte['sello'] ?? '') : '', //E Serie (vacío si impreso)
                $tieneFE ? $codigoGeneracion : $correlativo, //F Numero Interno del (código generación si DTE, correlativo si impreso)
                $tieneFE ? $codigoGeneracion : $correlativo, //G Numero Interno al (código generación si DTE, correlativo si impreso)
                $tieneFE ? '' : $correlativo, //H Numero Control (vacío si DTE, correlativo si impreso)
                $tieneFE ? '' : $correlativo, //I Numero Control (vacío si DTE, correlativo si impreso)
                NULL, //J Caja registradora
                $venta->exenta ? number_format($venta->exenta, 2, '.', '') : '0.00', //K Exentas
                '0.00', //L No Exentas no sujetas a proporcionalidad
                $venta->no_sujeta ? number_format($venta->no_sujeta, 2, '.', '') : '0.00', //M No Sujetas
                $esFacturaExportacion ? '0.00' : number_format($venta->gravada, 2, '.', ''), //N Gravadas'
                $esFacturaExportacion ? number_format(max(0, (float) $venta->total - $cuentaTerceros), 2, '.', ''): '0.00', //O Exportacion internas (propio, sin terceros)'
                '0.00', //P Exportacion externas'
                '0.00', //Q Exportacion servicios'
                '0.00', //R Ventas zonas francas'
                number_format($cuentaTerceros, 2, '.', ''), //S Ventas a terceros
                $venta->total ? number_format($venta->total, 2, '.', '') : '0.00', //T Total
                $this->tipoOperacion($venta->tipo_operacion), //U Tipo operacion renta 1 Gravada 2 Exenta
                $this->tipoRenta($venta->tipo_renta), //V Tipo ingreso renta
                2, //W num de Anexo

         ];
        return $fields;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '',
            'use_bom' => false,
        ];
    }

    function tipoOperacion($operacion) {
        switch ($operacion) {
            case 'Gravada': return 1;
            case 'No Gravada': return 2;
            case 'Excluido': return 3;
            case 'Mixta': return 4;
            default: return '0';
        }
    }

    function tipoRenta($tipo) {
        switch ($tipo) {
            case 'Profesiones, Artes y Oficios': return 1;
            case 'Actividades de Servicios': return 2;
            case 'Actividades Comerciales': return 3;
            case 'Actividades Industriales': return 4;
            case 'Actividades Agropecuarias': return 5;
            case 'Utilidades y Dividendos': return 6;
            case 'Exportaciones de bienes': return 7;
            case 'Servicios Realizados en el Exterior y Utilizados en El Salvador': return 8;
            case 'Exportaciones de servicios': return 9;
            case 'Otras Rentas Gravables': return 10;
            case 'Ingresos que ya fueron sujetos de retención informados en el F14 y consolidados en F910': return 12;
            case 'Sujetos pasivos excluidos': return 13;
            default: return null;
        }
    }


}