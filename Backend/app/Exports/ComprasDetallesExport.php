<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use App\Models\Compras\Detalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class ComprasDetallesExport implements FromCollection, WithHeadings, WithMapping
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
        $empresa = Auth::check() ? Auth::user()->empresa : null;
        if (!$empresa && $this->request && $this->request->id_empresa) {
            $empresa = Empresa::find($this->request->id_empresa);
        }

        return[
            'Fecha',
            'Proveedor',
            'DUI',
            'NIT',
            'Producto',
            'Categoria',
            'Documento',
            'Referencia',
            'Proyecto',
            'Num Identificacion',
            'Estado',
            'Vencimiento',
            'Cantidad',
            'Costo',
            'Sub Total',
            CountryTermsHelper::tax('taxLabel', $empresa),
            'Descuento',
            'Percepción',
            'Total',
        ];
    }

    public function collection()
    {
        $request = $this->request;
        
        $detalles = Detalle::whereHas('compra', function ($query) use ($request) {
                            $query->when($request->inicio, function ($query) use ($request) {
                                    return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
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
                                ->when($request->id_usuario, function ($query) use ($request) {
                                    return $query->where('id_usuario', $request->id_usuario);
                                })
                                ->when($request->id_proveedor, function ($query) use ($request) {
                                    return $query->where('id_proveedor', $request->id_proveedor);
                                })
                                ->when($request->forma_pago, function ($query) use ($request) {
                                    return $query->where('forma_pago', $request->forma_pago);
                                })
                                ->when($request->estado, function ($query) use ($request) {
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->metodo_pago, function ($query) use ($request) {
                                    return $query->where('metodo_pago', $request->metodo_pago);
                                })
                                ->when($request->id_proyecto, function ($query) use ($request) {
                                    return $query->where('id_proyecto', $request->id_proyecto);
                                })
                                ->when($request->dte && $request->dte == 0, function ($query) {
                                    return $query->whereNull('sello_mh');
                                })
                                ->when($request->dte && $request->dte == 1, function ($query) {
                                    return $query->whereNotNull('sello_mh');
                                })
                                ->where('cotizacion', 0)
                                ->when($request->buscador, function ($query) use ($request) {
                                    return $query->whereHas('proveedor', function ($q) use ($request) {
                                                $q->where('nombre', 'like', '%' . $request->buscador . '%')
                                                    ->orWhere('nombre_empresa', 'like', '%' . $request->buscador . '%')
                                                    ->orWhere('ncr', 'like', '%' . $request->buscador . '%')
                                                    ->orWhere('nit', 'like', '%' . $request->buscador . '%');
                                            })->orWhere('referencia', 'like', '%' . $request->buscador . '%')
                                            ->orWhere('estado', 'like', '%' . $request->buscador . '%')
                                            ->orWhere('observaciones', 'like', '%' . $request->buscador . '%')
                                            ->orWhere('forma_pago', 'like', '%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->orderBy('id', 'desc');
                        })->get();

        $devoluciones = $this->queryDevolucionesDetalles();

        return $detalles->concat($devoluciones)
            ->sortByDesc(function ($row) {
                $fecha = DevolucionEnReporte::esDevolucion($row)
                    ? ($row->fecha ?? '')
                    : optional($row->compra)->fecha;
                return sprintf('%s-%010d', (string) $fecha, (int) ($row->id ?? 0));
            })
            ->values();
        
    }

    private function queryDevolucionesDetalles()
    {
        $request = $this->request;
        $idEmpresa = Auth::check() ? Auth::user()->id_empresa : ($request->id_empresa ?? null);

        return DB::table('detalles_devolucion_compra as ddc')
            ->join('devoluciones_compra as d', 'd.id', '=', 'ddc.id_devolucion_compra')
            ->leftJoin('compras as c', 'c.id', '=', 'd.id_compra')
            ->leftJoin('proveedores as p', 'p.id', '=', 'd.id_proveedor')
            ->leftJoin('productos as pr', 'pr.id', '=', 'ddc.id_producto')
            ->leftJoin('categorias as cat', 'cat.id', '=', 'pr.id_categoria')
            ->leftJoin('proyectos as py', 'py.id', '=', 'c.id_proyecto')
            ->where('d.enable', 1)
            ->when($idEmpresa, function ($query) use ($idEmpresa) {
                return $query->where('d.id_empresa', $idEmpresa);
            })
            ->when($request->inicio, function ($query) use ($request) {
                return $query->whereBetween('d.fecha', [$request->inicio, $request->fin]);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('d.id_sucursal', $request->id_sucursal);
            })
            ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                return $query->whereIn('d.id_sucursal', $request->sucursales);
            })
            ->when($request->id_bodega, function ($query) use ($request) {
                return $query->where('d.id_bodega', $request->id_bodega);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('d.id_usuario', $request->id_usuario);
            })
            ->when($request->id_proveedor, function ($query) use ($request) {
                return $query->where('d.id_proveedor', $request->id_proveedor);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('c.id_proyecto', $request->id_proyecto);
            })
            ->select([
                'ddc.id',
                'd.fecha',
                'p.tipo as proveedor_tipo',
                'p.nombre as proveedor_nombre',
                'p.apellido as proveedor_apellido',
                'p.nombre_empresa as proveedor_empresa',
                'p.dui as proveedor_dui',
                'p.nit as proveedor_nit',
                'pr.nombre as producto_nombre',
                'cat.nombre as categoria_nombre',
                'd.tipo_documento',
                'd.referencia',
                'py.nombre as proyecto_nombre',
                'c.num_identificacion',
                'c.fecha_pago',
                'ddc.cantidad',
                'ddc.costo',
                'ddc.total',
                'ddc.subtotal',
                'ddc.iva',
                'ddc.descuento',
            ])
            ->get()
            ->each(function ($row) {
                DevolucionEnReporte::marcar($row);
            });
    }

    public function map($row): array{
        if (DevolucionEnReporte::esDevolucion($row)) {
            $nombreProveedor = 'Consumidor Final';
            if (($row->proveedor_tipo ?? null) == 'Empresa') {
                $nombreProveedor = $row->proveedor_empresa;
            } elseif ($row->proveedor_nombre) {
                $nombreProveedor = trim($row->proveedor_nombre . ' ' . ($row->proveedor_apellido ?? ''));
            }
            $subTotal = (float) ($row->subtotal ?? $row->total ?? 0);
            $iva = (float) ($row->iva ?? 0);
            $percepcion = 0.0;
            $cantidad = (float) $row->cantidad;

            return [
                $row->fecha,
                $nombreProveedor,
                $row->proveedor_dui,
                $row->proveedor_nit,
                $row->producto_nombre,
                $row->categoria_nombre,
                $row->tipo_documento ?: 'Devolución',
                $row->referencia,
                $row->proyecto_nombre,
                $row->num_identificacion,
                DevolucionEnReporte::ESTADO,
                $row->fecha_pago,
                DevolucionEnReporte::negar($cantidad),
                round((float) $row->costo, 2),
                DevolucionEnReporte::negar($subTotal),
                DevolucionEnReporte::negar($iva ?: $subTotal * 0.13),
                DevolucionEnReporte::negar($row->descuento ?? 0),
                DevolucionEnReporte::negar($percepcion),
                DevolucionEnReporte::negar($subTotal + ($iva ?: $subTotal * 0.13) + $percepcion),
            ];
        }

           $fields = [
              $row->compra()->pluck('fecha')->first(),
              $row->compra()->first() ? $row->compra()->first()->nombre_proveedor : 'Comsumidor Final',
              $row->compra()->first()->proveedor()->pluck('dui')->first(),
              $row->compra()->first()->proveedor()->pluck('nit')->first(),
              $row->producto()->pluck('nombre')->first(),
              $row->producto()->first() ? $row->producto()->first()->categoria()->pluck('nombre')->first() : '',
              $row->compra()->pluck('tipo_documento')->first(),
              $row->compra()->pluck('referencia')->first(),
              $row->compra()->first()->nombre_proyecto,
              $row->compra()->pluck('num_identificacion')->first(),
              $row->compra()->pluck('estado')->first(),
              $row->compra()->pluck('fecha_pago')->first(),
              $row->cantidad,
              $row->costo,
              $row->total,
              $row->total * 0.13,
              $row->descuento,
              $row->percepcion,
              $row->total + ($row->total * 0.13) + $row->percepcion,

         ];
        return $fields;
    }
}
