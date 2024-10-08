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
                        ->where('estado', '!=', 'Pendiente')
                        ->whereHas('documento', function($q) {
                            $q->where('nombre', 'Factura');
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();
        return $ventas;
        
    }

    public function map($row): array{

            $nombre = $row->dte['receptor']['nombre'] ?? '';
            $dui = $row->dte['receptor']['numDocumento'] ?? '';

           $fields = [
              \Carbon\Carbon::parse($row->fecha)->format('d/m/Y'), //'Fecha',
              '4', //'Clase',
              '01', //'Tipo',
              $row->dte['identificacion']['numeroControl'] ?? '', //'Resolucion',
              $row->dte['sello'] ?? '', //'Serie',
              $row->dte['identificacion']['codigoGeneracion'] ?? '', //'Numero',
              $row->dte['identificacion']['codigoGeneracion'] ?? '', //'Numero',
              trim($row->correlativo), //'Numero Interno',
              trim($row->correlativo), //'Numero Interno',
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
