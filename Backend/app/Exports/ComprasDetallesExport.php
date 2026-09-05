<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle;
use App\Models\Compras\Proveedores\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ComprasDetallesExport implements FromCollection, WithHeadings, WithMapping
{
    public $request;

    /** @var int|null Empresa para filtrar (sesión o reportes automáticos vía request). */
    protected ?int $idEmpresaFiltro = null;

    public function filter(Request $request)
    {
        $this->request = $request;
        if (auth()->check()) {
            $this->idEmpresaFiltro = (int) auth()->user()->id_empresa;
        } elseif ($request->filled('id_empresa')) {
            $this->idEmpresaFiltro = (int) $request->id_empresa;
        } else {
            $this->idEmpresaFiltro = null;
        }
    }

    public function headings(): array
    {
        $empresa = Auth::check() ? Auth::user()->empresa : null;
        if (!$empresa && $this->request && $this->request->id_empresa) {
            $empresa = Empresa::find($this->request->id_empresa);
        }

        return [
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

    public function collection(): Collection
    {
        $detalles = $this->query()->get();
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

    /**
     * Eager load de compra/proveedor/producto evita N+1 en map().
     * El orden por columnas de compra usa subconsulta (orderBy dentro de whereHas no ordenaba detalles).
     */
    public function query()
    {
        $request = $this->request;
        if (!$request) {
            return Detalle::query()->whereRaw('1 = 0');
        }

        $columnasOrdenPermitidas = ['id', 'fecha', 'total', 'estado', 'created_at', 'num_identificacion', 'referencia'];
        $orden = in_array($request->orden ?? '', $columnasOrdenPermitidas, true) ? $request->orden : 'fecha';
        $direccion = in_array(strtolower((string) ($request->direccion ?? '')), ['asc', 'desc'], true)
            ? strtolower($request->direccion)
            : 'desc';

        $idEmpresa = $this->idEmpresaFiltro;

        $ordenComprasSub = Compra::query()
            ->select($orden)
            ->whereColumn('compras.id', 'detalles_compra.id_compra')
            ->limit(1);

        $idCompraSub = Compra::query()
            ->select('id')
            ->whereColumn('compras.id', 'detalles_compra.id_compra')
            ->limit(1);

        return Detalle::query()
            ->select('detalles_compra.*')
            ->with([
                'compra' => static function ($q) {
                    $q->select(
                        'id',
                        'fecha',
                        'id_proveedor',
                        'id_proyecto',
                        'tipo_documento',
                        'referencia',
                        'num_identificacion',
                        'estado',
                        'fecha_pago'
                    );
                },
                'compra.proveedor:id,tipo,nombre,apellido,nombre_empresa,dui,nit',
                'compra.proyecto:id,nombre',
                'producto' => static function ($q) {
                    $q->withoutGlobalScopes()->select('id', 'nombre', 'id_categoria');
                },
                'producto.categoria:id,nombre',
            ])
            ->whereHas('compra', function ($query) use ($request, $idEmpresa) {
                $query->when($idEmpresa !== null, function ($q) use ($idEmpresa) {
                    $q->where('compras.id_empresa', $idEmpresa);
                })
                    ->when($request->inicio, function ($query) use ($request) {
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
                    ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                        return $query->whereIn('id_sucursal', $request->sucursales);
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
                    });
            })
            ->orderBy($ordenComprasSub, $direccion)
            ->orderBy($idCompraSub, 'desc')
            ->orderBy('detalles_compra.id', 'desc');
    }

    private function queryDevolucionesDetalles()
    {
        $request = $this->request;
        $idEmpresa = $this->idEmpresaFiltro
            ?? (Auth::check() ? Auth::user()->id_empresa : ($request->id_empresa ?? null));

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
            ->select(array_merge([
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
            ], self::columnasMontoDevolucionSiExisten($this->columnasMontoDevolucionEnBd())))
            ->get()
            ->each(function ($row) {
                DevolucionEnReporte::marcar($row);
            });
    }

    /**
     * subtotal/iva/descuento no siempre existen en producción.
     *
     * @param  array<int, string>  $columnasExistentes
     * @return array<int, string>
     */
    public static function columnasMontoDevolucionSiExisten(array $columnasExistentes): array
    {
        $select = [
            'ddc.cantidad',
            'ddc.costo',
            'ddc.total',
        ];

        foreach (['subtotal', 'iva', 'descuento'] as $columna) {
            if (in_array($columna, $columnasExistentes, true)) {
                $select[] = 'ddc.' . $columna;
            }
        }

        return $select;
    }

    /**
     * @return array<int, string>
     */
    private function columnasMontoDevolucionEnBd(): array
    {
        $existentes = [];
        foreach (['subtotal', 'iva', 'descuento'] as $columna) {
            if (Schema::hasColumn('detalles_devolucion_compra', $columna)) {
                $existentes[] = $columna;
            }
        }

        return $existentes;
    }

    public function map($row): array
    {
        if (DevolucionEnReporte::esDevolucion($row)) {
            return $this->mapDevolucionDetalle($row);
        }

        return $this->mapCompraDetalle($row);
    }


    private function mapCompraDetalle(Detalle $row): array
    {
        /** @var Compra|null $compra */
        $compra = $row->compra;
        $proveedor = ($compra && $compra->relationLoaded('proveedor')) ? $compra->proveedor : null;
        $producto = $row->producto;
        $categoriaNombre = ($producto && $producto->relationLoaded('categoria') && $producto->categoria)
            ? $producto->categoria->nombre
            : '';

        $subTotal = (float) $row->total;
        $iva = $subTotal * 0.13;
        $percepcion = (float) ($row->percepcion ?? 0);

        return [
            $compra ? $compra->fecha : null,
            $this->nombreProveedorParaExport($compra, $proveedor),
            $proveedor ? $proveedor->dui : null,
            $proveedor ? $proveedor->nit : null,
            $producto ? $producto->nombre : null,
            $categoriaNombre,
            $compra ? $compra->tipo_documento : null,
            $compra ? $compra->referencia : null,
            ($compra && $compra->relationLoaded('proyecto') && $compra->proyecto)
                ? $compra->proyecto->nombre
                : null,
            $compra ? $compra->num_identificacion : null,
            $compra ? $compra->estado : null,
            $compra ? $compra->fecha_pago : null,
            $row->cantidad,
            $row->costo,
            $row->total,
            $iva,
            $row->descuento,
            $percepcion,
            $subTotal + $iva + $percepcion,
        ];
    }

    /**
     * Mismo criterio que Compra::getNombreProveedorAttribute sin disparar el accessor (evita N+1).
     */
    protected function nombreProveedorParaExport(?Compra $compra, ?Proveedor $proveedor): string
    {
        if (!$compra) {
            return 'Consumidor Final';
        }
        if (!$proveedor) {
            return 'Consumidor Final';
        }
        if ($proveedor->tipo == 'Empresa') {
            return (string) $proveedor->nombre_empresa;
        }

        return trim($proveedor->nombre . ' ' . $proveedor->apellido);
    }

    private function mapDevolucionDetalle(object $row): array
    {
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
}
