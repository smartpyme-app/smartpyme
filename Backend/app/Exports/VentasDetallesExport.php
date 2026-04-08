<?php

namespace App\Exports;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class VentasDetallesExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    /**
     * @var Request|null
     */
    public $request;

    /** @var bool */
    protected $incluirPaquetes = false;

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

        $empresa = $this->idEmpresaFiltro ? Empresa::find($this->idEmpresaFiltro) : null;
        $this->incluirPaquetes = $empresa && !empty($empresa->modulo_paquetes);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings():array{
        $headings = [
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
            'Sucursal',
        ];
        if ($this->incluirPaquetes) {
            $headings = array_merge($headings, [
                'WR',
                'Núm. guía',
                'Núm. seguimiento',
            ]);
        }

        return $headings;
    }

    /**
     * Query sin ->get(); WithChunkReading escribe en lotes y el eager load evita N+1 en map().
     * El orden por columnas de venta usa subconsulta (orderBy dentro de whereHas no ordenaba detalles).
     */
    public function query()
    {
        $request = $this->request;
        if (!$request) {
            return Detalle::query()->whereRaw('1 = 0');
        }

        $columnasOrdenPermitidas = ['id', 'fecha', 'correlativo', 'total', 'estado', 'created_at', 'num_identificacion'];
        $orden = in_array($request->orden ?? '', $columnasOrdenPermitidas, true) ? $request->orden : 'fecha';
        $direccion = in_array(strtolower((string) ($request->direccion ?? '')), ['asc', 'desc'], true)
            ? strtolower($request->direccion)
            : 'desc';

        $idEmpresa = $this->idEmpresaFiltro;

        $ordenVentasSub = Venta::query()
            ->select($orden)
            ->whereColumn('ventas.id', 'detalles_venta.id_venta')
            ->limit(1);

        $idVentaSub = Venta::query()
            ->select('id')
            ->whereColumn('ventas.id', 'detalles_venta.id_venta')
            ->limit(1);

        $with = [
            'venta' => static function ($q) {
                $q->select(
                    'id',
                    'fecha',
                    'id_cliente',
                    'id_documento',
                    'id_canal',
                    'id_proyecto',
                    'id_sucursal',
                    'id_usuario',
                    'num_identificacion',
                    'correlativo',
                    'forma_pago',
                    'detalle_banco',
                    'estado',
                    'observaciones',
                    'iva',
                    'gravada',
                    'sub_total'
                );
            },
            'venta.cliente:id,tipo,nombre,apellido,nombre_empresa,telefono,dui,nit',
            'venta.documento:id,nombre',
            'venta.canal:id,nombre',
            'venta.sucursal:id,nombre,id_empresa',
            'venta.sucursal.empresa:id,nombre',
            'venta.usuario:id,name',
            'producto' => static function ($q) {
                $q->withoutGlobalScopes()->select('id', 'nombre', 'codigo', 'marca', 'id_categoria');
            },
            'producto.categoria:id,nombre',
            'vendedor:id,name',
        ];
        if ($this->incluirPaquetes) {
            $with['paquete'] = static function ($q) {
                $q->withTrashed();
            };
        }

        return Detalle::query()
            ->select('detalles_venta.*')
            ->with($with)
            ->whereHas('venta', function ($query) use ($request, $idEmpresa) {
                $query->when($idEmpresa !== null, function ($q) use ($idEmpresa) {
                    $q->where('ventas.id_empresa', $idEmpresa);
                })
                    ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($q) use ($request) {
                        $q->whereIn('ventas.id_sucursal', $request->sucursales);
                    })
                    ->when($request->inicio, function ($query) use ($request) {
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
                        }

                        return $query->where('id_documento', $request->id_documento);
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
                    });
            })
            ->orderBy($ordenVentasSub, $direccion)
            ->orderBy($idVentaSub, 'desc')
            ->orderBy('detalles_venta.id', 'desc');
    }

    public function map($row): array{
        /** @var Venta|null $venta */
        $venta = $row->venta;
        $documentoNombre = ($venta && $venta->documento) ? $venta->documento->nombre : null;
        $esFacturaExportacion = strtolower((string) $documentoNombre) === 'factura de exportación';

        $iva = 0;
        if ($venta && $venta->iva > 0 && !$esFacturaExportacion) {
            $ivaDetalle = $row->iva ?? 0;
            if ($ivaDetalle > 0) {
                $iva = $ivaDetalle;
            } else {
                $gravadaVenta = $venta->gravada ?? 0;
                $gravadaDetalle = $row->gravada ?? 0;
                $subTotalVenta = $venta->sub_total ?? 0;
                if ($gravadaVenta > 0 && $gravadaDetalle > 0) {
                    $iva = ($gravadaDetalle / $gravadaVenta) * $venta->iva;
                } elseif ($subTotalVenta > 0 && $row->total > 0) {
                    $iva = ($row->total / $subTotalVenta) * $venta->iva;
                }
            }
        }

        $totalConIva = $row->total + $iva;

        $producto = $row->producto;
        $categoriaNombre = ($producto && $producto->relationLoaded('categoria') && $producto->categoria)
            ? $producto->categoria->nombre
            : '';

        $cliente = ($venta && $venta->cliente) ? $venta->cliente : null;

        $fields = [
            $venta ? $venta->fecha : null,
            $this->nombreClienteParaExport($venta),
            $cliente ? $cliente->telefono : null,
            $cliente ? $cliente->dui : null,
            $cliente ? $cliente->nit : null,
            $producto ? $producto->nombre : null,
            $producto ? $producto->codigo : null,
            $producto ? $producto->marca : null,
            $categoriaNombre,
            $documentoNombre,
            $row->nombre_proyecto,
            $venta ? $venta->num_identificacion : null,
            $venta ? $venta->correlativo : null,
            $venta ? $venta->forma_pago : null,
            $venta ? $venta->detalle_banco : null,
            $venta ? $venta->estado : null,
            ($venta && $venta->canal) ? $venta->canal->nombre : null,
            $row->cantidad,
            round($row->costo,2),
            round($row->precio,2),
            round($row->descuento,2),
            round($iva,2),
            round($row->total - ($row->costo * $row->cantidad),2),
            round($totalConIva,2),
            ($venta && $venta->sucursal && $venta->sucursal->empresa) ? $venta->sucursal->empresa->nombre : null,
            $venta ? $venta->observaciones : null,
            ($venta && $venta->usuario) ? $venta->usuario->name : null,
            $row->vendedor ? $row->vendedor->name : null,
            ($venta && $venta->sucursal) ? $venta->sucursal->nombre : null,
        ];
        if ($this->incluirPaquetes) {
            $paquete = $this->resolvePaqueteParaDetalle($row, $venta);
            $fields[] = $paquete ? $paquete->wr : '';
            $fields[] = $paquete ? $paquete->num_guia : '';
            $fields[] = $paquete ? $paquete->num_seguimiento : '';
        }
        return $fields;
    }

    /**
     * Mismo criterio que Venta::getNombreClienteAttribute sin disparar el accessor (evita N+1).
     */
    protected function nombreClienteParaExport(?Venta $venta): string
    {
        if (!$venta) {
            return 'Comsumidor Final';
        }
        $cliente = $venta->cliente;
        if (!$cliente) {
            return 'Consumidor Final';
        }
        if ($cliente->tipo == 'Empresa') {
            return (string) $cliente->nombre_empresa;
        }
        return trim($cliente->nombre . ' ' . $cliente->apellido);
    }

    /**
     * Resuelve el paquete asociado al detalle, incluyendo borrados lógicos y casos donde id_venta_detalle quedó desincronizado.
     */
    protected function resolvePaqueteParaDetalle(Detalle $row, $venta): ?Paquete
    {
        $paquete = $row->relationLoaded('paquete') ? $row->paquete : null;
        if (!$paquete) {
            $paquete = $row->paquete()->withTrashed()->first();
        }

        if ($paquete || !$venta) {
            return $paquete;
        }

        $empresaId = $this->idEmpresaFiltro ?? (auth()->check() ? (int) auth()->user()->id_empresa : null);
        if ($empresaId === null) {
            return null;
        }
        $porVenta = Paquete::withTrashed()
            ->where('id_empresa', $empresaId)
            ->where('id_venta', $venta->id)
            ->get();

        if ($porVenta->count() === 1) {
            return $porVenta->first();
        }

        return null;
    }
}
