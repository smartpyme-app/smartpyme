<?php

namespace App\Exports\Contabilidad;

use App\Models\Compras\Compra;
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

    public function map($compra): array{

        $proveedor = optional($compra->proveedor);

        return [
            $this->index++,
            $compra->fecha,
            $compra->referencia,
            $proveedor->nit ?? $proveedor->ncr,
            $compra->nombre_proveedor,
            $compra->exenta,
            0,
            $compra->sub_total,
            0,
            $compra->iva,
            $compra->iva_percibido,
            $compra->total,
            0,
        ];

    }
}
