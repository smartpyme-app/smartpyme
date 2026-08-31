<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class ComprasExport implements FromCollection, WithHeadings, WithMapping
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
            'Proveedor',
            'DUI',
            'NIT',
            'Documento',
            'Referencia',
            'Proyecto',
            'Num identificación',
            'Estado', 
            'Vencimiento', 
            'Costo',
            'IVA', 
            'Percepción', 
            'Descuento', 
            'Total',
        ];

    }

    public function collection()
    {
        $request = $this->request;
        
        $compras = Compra::when($request->buscador, function($query) use ($request){
                        return $query->orwhere('correlativo', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('estado', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('observaciones', 'like', '%'.$request->buscador.'%')
                                    ->orwhere('forma_pago', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->recurrente !== null, function($q) use ($request){
                            $q->where('recurrente', !!$request->recurrente);
                        })
                        ->when($request->id_proyecto, function($q) use ($request){
                            $q->where('id_proyecto', $request->id_proyecto);
                        })
                        ->when($request->num_identificacion, function($q) use ($request){
                            $q->where('num_identificacion', $request->num_identificacion);
                        })
                        ->when($request->id_sucursal, function($query) use ($request){
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->where('id_proveedor', $request->id_proveedor);
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
                        ->where('cotizacion', 0)
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->get();

        $devoluciones = $this->queryDevoluciones()->get()->each(function (Devolucion $devolucion) {
            DevolucionEnReporte::marcar($devolucion);
        });

        return $compras->concat($devoluciones)
            ->sortByDesc(function ($row) {
                return sprintf('%s-%010d', (string) $row->fecha, (int) ($row->id ?? 0));
            })
            ->values();
        
    }

    private function queryDevoluciones()
    {
        $request = $this->request;
        $idEmpresa = Auth::check() ? Auth::user()->id_empresa : ($request->id_empresa ?? null);

        return Devolucion::withoutGlobalScopes()
            ->with(['proveedor', 'compra.proyecto'])
            ->where('enable', 1)
            ->when($idEmpresa, function ($query) use ($idEmpresa) {
                return $query->where('id_empresa', $idEmpresa);
            })
            ->when($request->inicio, function ($query) use ($request) {
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                return $query->whereIn('id_sucursal', $request->sucursales);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('referencia', 'like', '%'.$request->buscador.'%')
                        ->orWhere('observaciones', 'like', '%'.$request->buscador.'%')
                        ->orWhere('tipo_documento', 'like', '%'.$request->buscador.'%');
                });
            });
    }

    public function map($row): array{
        if (DevolucionEnReporte::esDevolucion($row)) {
            $proveedor = $row->proveedor;
            $compra = $row->compra;
            return [
                $row->fecha,
                $row->nombre_proveedor,
                $proveedor ? $proveedor->dui : '',
                $proveedor ? $proveedor->nit : '',
                $row->tipo_documento ?: 'Devolución',
                $row->referencia,
                ($compra && $compra->proyecto) ? $compra->proyecto->nombre : '',
                $compra ? $compra->num_identificacion : '',
                DevolucionEnReporte::ESTADO,
                $compra ? $compra->fecha_pago : '',
                DevolucionEnReporte::negar($row->sub_total),
                DevolucionEnReporte::negar($row->iva),
                DevolucionEnReporte::negar($row->iva_percibido ?? 0),
                DevolucionEnReporte::negar($row->descuento),
                DevolucionEnReporte::negar($row->total),
            ];
        }

           $fields = [
              $row->fecha,
              $row->nombre_proveedor,
              $row->proveedor()->pluck('dui')->first(),
              $row->proveedor()->pluck('nit')->first(),
              $row->tipo_documento,
              $row->referencia,
              $row->proyecto()->pluck('nombre')->first(),
              $row->num_identificacion,
              $row->estado,
              $row->fecha_pago,
              $row->sub_total,
              $row->iva,
              $row->percepcion,
              $row->descuento,
              $row->total,
         ];
        return $fields;
    }
}
