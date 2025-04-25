<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoContribuyentesExport implements FromCollection, WithMapping
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

            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            $tipo = '03'; //CCF

            if ($documento && $documento->nombre == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($documento && $documento->nombre == 'Nota de débito') {
                $tipo = '06';
            }

            $fields = [
                \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), //A Fecha
                $venta->sello_mh ? '4' : '1', //B Clase DTE o Impreso,
                $tipo, //C Tipo,
                $venta->dte['identificacion']['numeroControl'] ?? '', //'D Num Resolucion
                $venta->dte['sello'] ?? '', //E Num Serie
                $venta->sello_mh ? $venta->dte['identificacion']['codigoGeneracion'] : trim($venta->correlativo), //'F Num Documento
                $venta->sello_mh ? '' : trim($venta->correlativo), //G Numero Control Interno
                $cliente->ncr ? $cliente->ncr : $cliente->nit , //H NIT/NRC
                $venta->dte['receptor']['nombre'] ?? $venta->nombre_cliente, //I Nombre
                $venta->id_venta ? $venta->exenta * -1 : $venta->exenta, // J Exentas
                $venta->id_venta ? $venta->no_sujeta * -1 : $venta->no_sujeta, // K No sujetas
                $venta->id_venta ? $venta->sub_total * -1 : $venta->sub_total, // L Gravadas
                $venta->id_venta ? $venta->iva * -1 : $venta->iva, // Debido fiscal
                '0.00', //N Ventas a terceros
                '0.00', //O Debito ventas a terceros
                $venta->id_venta ? $venta->total * -1 : $venta->total, // P total
                '', //Q DUI
                $venta->exenta > 0 ? 2 : 1, //R Tipo operacion renta 1 Gravada 2 Exenta
                '', //S Tipo ingreso renta
                1, //T num de Anexo

            ];

        return $fields;
    }

    // public function getCsvSettings(): array
    // {
    //     return [
    //         'delimiter' => ';',
    //         'use_bom' => true,
    //         'enclosure' => '',
    //     ];
    // }

}
