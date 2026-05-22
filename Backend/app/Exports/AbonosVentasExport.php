<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use App\Models\Ventas\Abono;

class AbonosVentasExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    private $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{

        return[
            'Fecha',
            'Cliente',
            'DUI',
            'Documento',
            'Correlativo',
            'Concepto',
            'Estado',
            'Forma pago',
            'Banco',
            'Referencia',
            'Total',
            'Nota',
        ];
    }

    public function collection()
    {
        $request = $this->request;

        return Abono::with(['venta', 'documento'])->when($request->buscador, function ($query) use ($request) {
                        $buscador = '%' . $request->buscador . '%';
                        return $query->where(function ($q) use ($buscador) {
                            $q->where('correlativo', 'like', $buscador)
                                ->orWhere('id_venta', 'like', $buscador)
                                ->orWhere('concepto', 'like', $buscador)
                                ->orWhere('nombre_de', 'like', $buscador)
                                ->orWhere('referencia', 'like', $buscador)
                                ->orWhereHas('venta', function ($qv) use ($buscador) {
                                    $qv->where('correlativo', 'like', $buscador)
                                        ->orWhereHas('cliente', function ($qc) use ($buscador) {
                                            $qc->where('nombre', 'like', $buscador)
                                                ->orWhere('apellido', 'like', $buscador)
                                                ->orWhere('nombre_empresa', 'like', $buscador)
                                                ->orWhereRaw("CONCAT(TRIM(nombre), ' ', TRIM(apellido)) LIKE ?", [$buscador]);
                                        });
                                });
                        });
                    })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_cliente, function($query) use ($request){
                            return $query->where('id_cliente', $request->id_cliente);
                        })
                        ->when($request->forma_pago, function($query) use ($request){
                            return $query->where('forma_pago', $request->forma_pago);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                    ->get();
        
    }

    public function map($row): array{
           $fields = [
              $row->fecha,
              $row->venta()->first() ? $row->venta()->first()->nombre_cliente : '',
              $row->venta()->first() ? $row->venta()->first()->cliente()->pluck('dui')->first() : '',
              $row->venta()->first() ? $row->venta()->first()->documento()->pluck('nombre')->first() : '',
              $row->correlativo ?? $row->id,
              $row->concepto,
              $row->estado == 'Confirmado' ? 'Pagado' : $row->estado,
              $row->forma_pago,
              $row->detalle_banco,
              $row->referencia,
              number_format($row->total,2),
              $row->nota,
         ];
        return $fields;
    }
}
