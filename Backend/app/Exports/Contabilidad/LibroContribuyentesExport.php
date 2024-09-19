<?php

namespace App\Exports\Contabilidad;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class LibroContribuyentesExport implements FromCollection, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    // public function headings():array{
    //     return[
    //         'Fecha',
    //         'Clase',
    //         'Tipo',
    //         'Resolucion',
    //         'Serie',
    //         'Numero',
    //         'Numero Interno',
    //         'NIT/NRC',
    //         'Nombre',
    //         'Exentas',
    //         'No Sujetas',
    //         'Gravadas', 
    //         'Debito', 
    //         'Ventas a terceros',
    //         'Debito ventas a terceros',
    //         'Total',
    //         'DUI',
    //         'Anexo',
    //     ];
    // }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::where('tipo_documento', $request->tipo_documento)
                                ->with('cliente')
                                ->where('estado', 'Cobrada')
                                ->whereBetween('fecha', [$request->inicio, $request->fin])
                                ->orderBy('fecha','asc')
                                ->get();
        return $ventas;
        
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
              '03', //'Tipo',
              $row->dte['identificacion']['numeroControl'] ?? '', //'Resolucion',
              $row->dte['sello'] ?? '', //'Serie',
              $row->dte['identificacion']['codigoGeneracion'] ?? '', //'Numero',
              trim($row->correlativo), //'Numero Interno',
              $nit_nrc, //'NIT/NRC',
              $nombre, //'Nombre',
              $row->exenta ? $row->exenta : '0.00', //'Exentas',
              $row->no_sujeta ? $row->no_sujeta : '0.00', //'No Sujetas',
              $row->subtotal ? $row->subtotal : '0.00', //'Gravadas', 
              $row->iva ? $row->iva : '0.00', //'Debito', 
              '0.00', //'Ventas a terceros',
              '0.00', //'Debito ventas a terceros',
              $row->total ? $row->total : '0.00', //'Total',
              null, //'DUI',
              1, //'Anexo',

         ];
        return $fields;
    }
}
