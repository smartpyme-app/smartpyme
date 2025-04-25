<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
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
        
        $compras = Compra::with(['proveedor'])
                            ->where('estado', '!=', 'Anulada')
                            ->when($request->id_sucursal, function($q) use ($request){
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('cotizacion', 0)
                            ->orderBy('id', 'desc')->get();
        return $compras;
        
    }

    public function map($row): array{

            $proveedor = $row->proveedor;

           $fields = [
              \Carbon\Carbon::parse($row->fecha)->format('d/m/Y'), //'Fecha',
              '4', //'Clase',
              '03', //'Tipo',
              $row->referencia,
              $proveedor->nit ?? $proveedor->ncr, //'NIT o NRC',
              $proveedor->nombre, //'NIT o NRC',
              $row->exenta + $row->no_sujeta, //'Exentas y no sujetas',
              '0.00', //'Internaciones Exentas y no sujetas',
              '0.00', //'Importaciones Exentas y no sujetas',
              $row->total ? $row->total : '0.00', //'Gravadas', 
              '0.00', //'Internaciones Gravadas', 
              '0.00', //'Importaciones Gravadas', 
              '0.00', //'Importaciones Gravadas Servicios', 
              $row->iva ? $row->iva : '0.00', //'Credito fiscal', 
              $row->total ? $row->total : '0.00', //'Total',
              $proveedor ? $proveedor->dui : '', //'DUI', 
              '0', //'Tipo de operacion', 
              '0', //'Clasificación', 
              '0', //'Sector', 
              '0', //'Tipo de costo/gasto', 
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
