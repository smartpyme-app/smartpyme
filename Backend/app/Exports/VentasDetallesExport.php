<?php

namespace App\Exports;

use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasDetallesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    public function headings():array{
        return[
            'Fecha',
            'Cliente',
            'Telefono',
            'DUI',
            'NIT',
            'Producto',
            'Codigo',
            'Marca',
            'Categoria',
            'Documento',
            'Proyecto',
            'Num Identificacion',
            'Correlativo',
            'Forma de pago',
            'Banco',
            'Estado',
            'Canal',
            'Cantidad',
            'Costo',
            'Precio',
            'Descuento',
            'IVA',
            'Utilidad',
            'Total',
            'Empresa',
            'Observaciones', 
            'Usuario',
            'Vendedor',
            'Sucursal'
        ];
    }

    public function collection()
    {
        $request = $this->request;
        
        $detalles = Detalle::whereHas('venta', function($query) use ($request) {
                              $query->when($request->inicio, function ($query) use ($request) {
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function ($query) use ($request) {
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->recurrente !== null, function ($q) use ($request) {
                                    $q->where('recurrente', !!$request->recurrente);
                                })
                                ->when($request->num_identificacion, function ($q) use ($request) {
                                    $q->where('num_identificacion', $request->num_identificacion);
                                })
                                ->when($request->id_sucursal, function ($query) use ($request) {
                                    return $query->where('id_sucursal', $request->id_sucursal);
                                })
                                ->when($request->id_bodega, function ($query) use ($request) {
                                    return $query->where('id_bodega', $request->id_bodega);
                                })
                                ->when($request->id_cliente, function ($query) use ($request) {
                                    return $query->where('id_cliente', $request->id_cliente);
                                })
                                ->when($request->id_usuario, function ($query) use ($request) {
                                    return $query->where('id_usuario', $request->id_usuario);
                                })
                                ->when($request->forma_pago, function ($query) use ($request) {
                                    return $query->where('forma_pago', $request->forma_pago)
                                        ->orwhereHas('metodos_de_pago', function ($query) use ($request) {
                                            $query->where('nombre', $request->forma_pago);
                                        });
                                })
                                ->when($request->id_vendedor, function ($query) use ($request) {
                                    return $query->where('id_vendedor', $request->id_vendedor)
                                        ->orwhereHas('detalles', function ($query) use ($request) {
                                            $query->where('id_vendedor', $request->id_vendedor);
                                        });
                                })
                                ->when($request->id_canal, function ($query) use ($request) {
                                    return $query->where('id_canal', $request->id_canal);
                                })
                                ->when($request->id_proyecto, function ($query) use ($request) {
                                    return $query->where('id_proyecto', $request->id_proyecto);
                                })
                                ->when($request->id_documento, function ($query) use ($request) {
                                    $documento = \App\Models\Admin\Documento::find($request->id_documento);
                                    if ($documento) {
                                        return $query->whereHas('documento', function ($q) use ($documento) {
                                            $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                                        });
                                    } else {
                                        return $query->where('id_documento', $request->id_documento);
                                    }
                                })
                                ->when($request->estado, function ($query) use ($request) {
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->metodo_pago, function ($query) use ($request) {
                                    return $query->where('metodo_pago', $request->metodo_pago);
                                })
                                ->when($request->tipo_documento, function ($query) use ($request) {
                                    return $query->whereHas('documento', function ($q) use ($request) {
                                        $q->where('nombre', $request->tipo_documento);
                                    });
                                })
                                ->when($request->dte && $request->dte == 1, function ($query) {
                                    return $query->whereNull('sello_mh');
                                })
                                ->when($request->dte && $request->dte == 2, function ($query) {
                                    return $query->whereNotNull('sello_mh');
                                })
                                ->where('cotizacion', 0)
                                ->when($request->buscador, function ($query) use ($request) {
                                    $buscador = '%' . $request->buscador . '%';
                                    return $query->where(function ($q) use ($buscador) {
                                        $q->whereHas('cliente', function ($qCliente) use ($buscador) {
                                            $qCliente->where('nombre', 'like', $buscador)
                                                ->orWhere('nombre_empresa', 'like', $buscador)
                                                ->orWhere('ncr', 'like', $buscador)
                                                ->orWhere('nit', 'like', $buscador);
                                        })
                                            ->orWhere('correlativo', 'like', $buscador)
                                            ->orWhere('estado', 'like', $buscador)
                                            ->orWhere('observaciones', 'like', $buscador)
                                            ->orWhere('forma_pago', 'like', $buscador);
                                    });
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->orderBy('id', 'desc');
                            })->get();

        return $detalles;
        
    }

    public function map($row): array{
           $fields = [
              $row->venta()->pluck('fecha')->first(),
              $row->venta()->first() ? $row->venta()->first()->nombre_cliente : 'Comsumidor Final',
              $row->venta()->first()->cliente()->pluck('telefono')->first(),
              $row->venta()->first()->cliente()->pluck('dui')->first(),
              $row->venta()->first()->cliente()->pluck('nit')->first(),
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->pluck('codigo')->first(),
              $row->producto()->pluck('marca')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->venta()->first()->documento()->pluck('nombre')->first(),
              $row->nombre_proyecto,
              $row->venta()->pluck('num_identificacion')->first(),
              $row->venta()->pluck('correlativo')->first(),
              $row->venta()->pluck('forma_pago')->first(),
              $row->venta()->pluck('detalle_banco')->first(),
              $row->venta()->pluck('estado')->first(),
              $row->venta()->first()->canal()->pluck('nombre')->first(),
              $row->cantidad,
              round($row->costo,2),
              round($row->precio,2),
              round($row->descuento,2),
              round($row->venta()->first()->iva ? $row->total * 0.13 : 0,2),
              round($row->total - ($row->costo * $row->cantidad),2),
              round($row->total + ($row->venta()->first()->iva ? $row->total * 0.13 : 0),2),
              $row->venta()->first()->sucursal()->first()->empresa()->pluck('nombre')->first(),
              $row->venta()->pluck('observaciones')->first(),
              $row->venta()->first()->usuario()->pluck('name')->first(),
              $row->vendedor()->pluck('name')->first(),
              $row->venta()->first()->sucursal()->pluck('nombre')->first()
         ];
        return $fields;
    }
}
