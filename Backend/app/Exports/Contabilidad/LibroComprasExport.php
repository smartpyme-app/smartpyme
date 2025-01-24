<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class LibroComprasExport implements FromCollection,WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $request;
    private $index = 1;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
        return[
            'N°',
            'FECHA',
            'NÚMERO DE DOCUMENTO',
            'NÚMERO DE REGISTRO DEL CONTRIBUYENTE',
            'NOMBRE DEL PROVEEDOR',
            'COMPRAS EXENTAS INTERNAS',
            'COMPRAS NO SUJETAS',
            'IMPORTACIONES E INTERNACIONES EXENTAS',
            'COMPRAS INTERNAS GRAVADAS',
            'IMPORTACIONES E INTERNACIONES GRAVADAS',
            'CRÉDITO FISCAL',
            'ANTICIPO A CUENTA IVA PERCIBIDO',
            'TOTAL',
            'COMPRAS A SUJETOS EXCLUIDOS',
        ];
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

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $libroCompras = $compras->merge($gastos)->merge($devoluciones)->sortBy('fecha');

        return $libroCompras;
        
    }

    public function map($compra): array{

        $proveedor = optional($compra->proveedor);

        return [
            $this->index++,
            $compra->fecha,
            $compra->referencia,
            $proveedor->nit ?? $proveedor->ncr,
            $compra->nombre_proveedor,
            $compra->id_compra ? $compra->exenta * -1 : $compra->exenta,
            $compra->id_compra ? $compra->no_sujeta * -1 : $compra->no_sujeta,
            0,
            $compra->id_compra ? $compra->sub_total * -1 : $compra->sub_total,
            0,
            $compra->id_compra ? $compra->iva * -1 : $compra->iva,
            $compra->id_compra ? $compra->iva_percibido * -1 : $compra->iva_percibido,
            $compra->id_compra ? $compra->total * -1 : $compra->total,
            0,
        ];

    }
}
