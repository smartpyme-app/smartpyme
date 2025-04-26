<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Compras\Gastos\Gasto;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoComprasExport implements FromCollection, WithMapping, WithCustomCsvSettings
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
        
        $compras = Compra::with('proveedor')
            ->whereHas('proveedor', function ($q) {
                $q->whereNotNull('dui')
                  ->orWhereNotNull('nit')
                  ->orWhereNotNull('ncr');
            })
            ->where('iva' , '>', 0)
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function($q) use ($request){
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderBy('id', 'desc')->get();

        $gastos = Gasto::with('proveedor')
            ->whereHas('proveedor', function ($q) {
                $q->whereNotNull('dui')
                  ->orWhereNotNull('nit')
                  ->orWhereNotNull('ncr');
            })
            ->where('iva' , '>', 0)
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, function ($q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $devoluciones = Devolucion::with('proveedor')
            ->whereHas('proveedor', function ($q) {
                $q->whereNotNull('dui')
                  ->orWhereNotNull('nit')
                  ->orWhereNotNull('ncr');
            })
            ->where('iva' , '>', 0)
            ->where('enable', true)
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->where('tipo_documento', 'Crédito fiscal')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();


        $libroCompras = $compras->merge($compras)->merge($devoluciones)->merge($gastos)->sortBy(function ($item) {
                return [$item['fecha']];
            });

        return $libroCompras;
        
    }

    public function map($compra): array{

            $proveedor = optional($compra->proveedor()->first());

            $tipo = '03'; //CCF

            if ($compra->tipo_documento == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($compra->tipo_documento == 'Nota de débito') {
                $tipo = '06';
            }

            if ($compra->tipo_documento == 'Factura de exportación') {
                $tipo = '11';
            }

            $fields = [
                \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y'), //A Fecha
                strlen($compra->referencia) >= 30 ? 4 : 1, // Clase DTE: 4 si tiene 30 caracteres, 1 si no
                $tipo, //C Tipo
                $compra->referencia, // D Num documento
                $proveedor->ncr ?? $proveedor->nit, //E NIT o NRC
                $compra->nombre_proveedor, //F Nombre
                ($compra->exenta + $compra->no_sujeta) > 0 ? $compra->exenta + $compra->no_sujeta : '0.00', //G Exentas y no sujetas
                '0.00', //H Internaciones Exentas y no sujetas
                '0.00', //I Importaciones Exentas y no sujetas
                $compra->sub_total, //J Gravadas'
                '0.00', //K Internaciones Gravadas'
                '0.00', //L Importaciones Gravadas'
                '0.00', //M Importaciones Gravadas Servicios'
                $compra->iva, //N Credito fiscal'
                $compra->total, //O Total
                (!$proveedor->nit && !$proveedor->ncr) ? str_replace('-', '', $proveedor->dui) : '', //P DUI'
                $compra->exenta > 0 ? 2 : 1, //Q Tipo operación renta 1 Gravada 2 Exenta
                1, //R Clasificación 1 costo 2 gasto
                2, //S Sector' 1 Industria 2 Comercio 3 Agropecuario 4 Servicios
                5, //T Tipo de costo/gasto'
                3, //U Anexo
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

}
