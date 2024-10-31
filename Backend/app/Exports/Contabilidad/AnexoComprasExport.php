<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoComprasExport implements FromCollection, WithMapping
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
        
        // Obtener las compras
        $compras = Compra::with(['proveedor'])
                            ->where('estado', '!=', 'Anulada')
                            ->when($request->id_sucursal, function($q) use ($request){
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('cotizacion', 0)
                            ->get();

        // Obtener los gastos
        $gastos = Gasto::with(['proveedor'])
                            ->where('estado', '!=', 'Anulada')
                            ->when($request->id_sucursal, function($q) use ($request) {
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->get();

        // Unir y ordenar ambas colecciones por fecha
        $libroCompras = $compras->merge($gastos)->sortBy('fecha');

        return $libroCompras;
        
    }

    public function map($row): array{

            $nombre = $row->dte['receptor']['nombre'] ?? '';
            $dui = $row->dte['receptor']['numDocumento'] ?? '';
            $nit_nrc = '';

            if ($row->dte && $row->tipo_documento == 'Credito Fiscal' && $row->dte['receptor']) {
                $nit_nrc = $row->dte['receptor']['nrc'] ? $row->dte['receptor']['nrc'] : $row->dte['receptor']['nit'];
            }

           $fields = [
              \Carbon\Carbon::parse($row->fecha)->format('d/m/Y'), //'Fecha',
              '4', //'Clase',
              '01', //'Tipo',
              $row->dte['identificacion']['numeroControl'] ?? '', //'Resolucion',
              $row->dte['sello'] ?? '', //'Serie',
              $row->dte['identificacion']['codigoGeneracion'] ?? $row->referencia, //'Numero',
              $row->dte['identificacion']['codigoGeneracion'] ?? $row->referencia, //'Numero',
              trim($row->referencia), //'Numero Interno',
              trim($row->referencia), //'Numero Interno',
              NULL, //'Caja registradora',
              $row->exenta ? $row->exenta : '0.00', //'Exentas',
              '0.00', //'No Exentas no sujetas a proporcionalidad',
              $row->no_sujeta ? $row->no_sujeta : '0.00', //'No Sujetas',
              $row->total ? $row->total : '0.00', //'Gravadas', 
              '0.00', //'Exportacion interna', 
              '0.00', //'Exportacion externa', 
              '0.00', //'Exportacion servicios', 
              '0.00', //'Ventas zonas francas', 
              '0.00', //'Ventas a terceros',
              $row->total ? $row->total : '0.00', //'Total',
              2, //'Anexo',

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
