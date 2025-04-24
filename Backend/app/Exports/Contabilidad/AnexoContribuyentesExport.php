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

        $devoluciones = DevolucionVenta::with(['cliente'])
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

    public function map($row): array{

            $documento = $row->documento;
            $cliente = optional($row->cliente);

            $nombre = $row->dte['receptor']['nombre'] ?? '';
            $dui = $row->dte['receptor']['numDocumento'] ?? '';
            $nit_nrc = '';

            if ($row->dte && $documento->nombre == 'Crédito fiscal' && isset($row->dte['receptor'])) {
                $nit_nrc = !empty($row->dte['receptor']['nrc']) ? $row->dte['receptor']['nrc'] : (!empty($row->dte['receptor']['nit']) ? $row->dte['receptor']['nit'] : '');
            }

           $fields = [
              \Carbon\Carbon::parse($row->fecha)->format('d/m/Y'), //'Fecha',
              '4', //'Clase',
              '03', //'Tipo',
              $row->dte['identificacion']['numeroControl'] ?? '', //'Resolucion',
              $row->dte['sello'] ?? '', //'Serie',
              $row->dte['identificacion']['codigoGeneracion'] ?? '', //'Numero',
              trim($row->correlativo), //'Numero Interno',
              $nit_nrc, //'NIT/NRC',
              $nombre, //'Nombre',
              $row->id_venta ? $row->exenta * -1 : $row->exenta,
              $row->id_venta ? $row->no_sujeta * -1 : $row->no_sujeta,
              $row->id_venta ? $row->sub_total * -1 : $row->sub_total,
              $row->id_venta ? $row->iva * -1 : $row->iva,
              '0.00', //'Ventas a terceros',
              '0.00', //'Debito ventas a terceros',
              $row->id_venta ? $row->total * -1 : $row->total,
              null, //'DUI',
              1, //'Anexo',

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
