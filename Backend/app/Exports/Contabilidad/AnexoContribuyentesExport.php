<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoContribuyentesExport implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Crédito fiscal');
                            });
                        })
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $devoluciones = DevolucionVenta::with(['cliente', 'documento'])
            ->where('enable', true)
            ->whereHas('venta', function ($query) {
                $query->where('estado', '!=', 'Anulada');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $libroVentas = $ventas->merge($ventas)->merge($devoluciones)->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            });

        return $libroVentas;
        
    }

    public function map($venta): array{
            setlocale(LC_NUMERIC, 'C');
            
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            $tipo = '03'; //CCF

            if ($documento && $documento->nombre == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($documento && $documento->nombre == 'Nota de débito') {
                $tipo = '06';
            }

            if ($venta->iva > 0) {
                $venta->gravada = $venta->sub_total;
            }else{
                $venta->gravada = 0;
                $venta->exenta = $venta->sub_total;
            }

            $fields = [
                \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), //A Fecha sin ceros a la izquierda
                $venta->sello_mh ? '4' : '1', //B Clase DTE o Impreso
                $tipo, //C Tipo
                $venta->sello_mh ? str_replace('-', '', $venta->dte['identificacion']['numeroControl'] ?? '') : '', //D Num Resolución
                $venta->sello_mh ?? '', //E Num Serie
                $venta->sello_mh ? str_replace('-', '', $venta->dte['identificacion']['codigoGeneracion'] ?? '') : trim($venta->correlativo), //F Num Documento
                $venta->sello_mh ? '' : trim($venta->correlativo), //G Número Control Interno
                $cliente->ncr ?? $cliente->nit, //H NIT/NRC
                isset($venta->dte['receptor']) ? $venta->dte['receptor']['nombre'] : $venta->nombre_cliente, //I Nombre
                number_format($venta->exenta, 2, '.', ''), //J Exentas (formato numérico con 2 decimales)
                number_format($venta->no_sujeta, 2, '.', ''), //K No sujetas (formato numérico con 2 decimales)
                number_format($venta->gravada, 2, '.', ''), //L Gravadas (formato numérico con 2 decimales)
                number_format($venta->iva, 2, '.', ''), //M Debido fiscal (formato numérico con 2 decimales)
                '0.00', //N Ventas a terceros
                '0.00', //O Débito ventas a terceros
                number_format($venta->total, 2, '.', ''), //P Total (formato numérico con 2 decimales)
                '', //Q DUI (vacío)
                $this->tipoOperacion($venta->tipo_operacion), //R Tipo operación renta 1 Gravada 2 Exenta
                $this->tipoRenta($venta->tipo_renta), //S Tipo ingreso renta
                1, //T Número de Anexo
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
            default: return '0';
        }
    }

}
