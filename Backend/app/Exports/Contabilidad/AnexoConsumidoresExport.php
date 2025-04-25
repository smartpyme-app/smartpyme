<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoConsumidoresExport implements FromCollection, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
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

            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            $tipo = '01'; //CF

            if ($documento && $documento->nombre == 'Factura de exportación') {
                $tipo = '11';
            }

           $fields = [
                \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), //A Fecha
                $venta->sello_mh ? '4' : '1', //B Clase DTE o Impreso,
                '01', //C Tipo
                $venta->dte['identificacion']['numeroControl'] ?? '', //D Resolucion
                $venta->dte['sello'] ?? '', //E Serie
                $venta->dte['identificacion']['codigoGeneracion'] ?? '', //F Numero Interno del
                $venta->dte['identificacion']['codigoGeneracion'] ?? '', //G Numero Interno al
                trim($venta->correlativo), //H Numero Control
                trim($venta->correlativo), //I Numero Control
                NULL, //J Caja registradora
                $venta->exenta ? $venta->exenta : '0.00', //K Exentas
                '0.00', //L No Exentas no sujetas a proporcionalidad
                $venta->no_sujeta ? $venta->no_sujeta : '0.00', //M No Sujetas
                $venta->documento->nombre === 'Factura de exportación' ? '0' : $venta->total, //N Gravadas'
                '0.00', //O Exportacion interna'
                $venta->documento->nombre === 'Factura de exportación' ? $venta->total : '0', //P Exportacion externa'
                '0.00', //Q Exportacion servicios'
                '0.00', //R Ventas zonas francas'
                '0.00', //S Ventas a terceros
                $venta->total ? $venta->total : '0.00', //T Total
                $venta->exenta > 0 ? 2 : 1, //U Tipo operacion renta 1 Gravada 2 Exenta
                '', //V Tipo ingreso renta
                2, //W num de Anexo

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
