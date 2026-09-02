<?php

namespace App\Exports;

use App\Constants\OrigenStockVentaConstants;
use App\Exports\Support\DevolucionEnReporte;
use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Paquete;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Devoluciones\Detalle as DetalleDevolucion;
use App\Models\Ventas\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VentasDetallesExport implements FromCollection, WithHeadings, WithMapping
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

    public function headings():array{
        $empresa = $this->idEmpresaFiltro ? Empresa::find($this->idEmpresaFiltro) : null;
        $taxLabel = CountryTermsHelper::tax('taxLabel', $empresa);

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
            $taxLabel,
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

    public function collection(): Collection
    {
        $detalles = $this->query()->get();
        $devoluciones = $this->queryDevolucionesDetalles()->get()->each(function (DetalleDevolucion $detalle) {
            DevolucionEnReporte::marcar($detalle);
        });

        return $detalles->concat($devoluciones)
            ->sortByDesc(function ($row) {
                $fecha = DevolucionEnReporte::esDevolucion($row)
                    ? optional($row->venta)->fecha
                    : optional($row->venta)->fecha;
                return sprintf('%s-%010d', (string) $fecha, (int) ($row->id ?? 0));
            })
            ->values();
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
            'venta.detalles:id,id_venta,origen_stock',
            'venta.sucursal:id,nombre,id_empresa',
            'venta.sucursal.empresa:id,nombre',
            'venta.usuario:id,name',
            'venta.vendedor:id,name',
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
            ->when($request->id_vendedor, function ($query) use ($request) {
                $idV = (int) $request->id_vendedor;
                $query->where(function ($q) use ($idV) {
                    $q->where('detalles_venta.id_vendedor', $idV)
                        ->orWhere(function ($q2) use ($idV) {
                            $q2->whereNull('detalles_venta.id_vendedor')
                                ->whereHas('venta', fn ($v) => $v->where('id_vendedor', $idV));
                        });
                });
            })
            ->orderBy($ordenVentasSub, $direccion)
            ->orderBy($idVentaSub, 'desc')
            ->orderBy('detalles_venta.id', 'desc');
    }

    public function queryDevolucionesDetalles()
    {
        $request = $this->request;
        if (!$request) {
            return DetalleDevolucion::query()->whereRaw('1 = 0');
        }

        $idEmpresa = $this->idEmpresaFiltro;

        return DetalleDevolucion::query()
            ->with([
                'venta' => static function ($q) {
                    $q->select(
                        'id',
                        'fecha',
                        'id_cliente',
                        'id_documento',
                        'id_sucursal',
                        'id_usuario',
                        'id_venta',
                        'correlativo',
                        'observaciones',
                        'iva',
                        'sub_total',
                        'total'
                    );
                },
                'venta.cliente:id,tipo,nombre,apellido,nombre_empresa,telefono,dui,nit',
                'venta.documento:id,nombre',
                'venta.sucursal:id,nombre,id_empresa',
                'venta.sucursal.empresa:id,nombre',
                'venta.usuario:id,name',
                'venta.venta:id,id_canal,id_proyecto,id_vendedor,forma_pago,detalle_banco,num_identificacion',
                'venta.venta.canal:id,nombre',
                'venta.venta.proyecto:id,nombre',
                'venta.venta.vendedor:id,name',
                'producto' => static function ($q) {
                    $q->withoutGlobalScopes()->select('id', 'nombre', 'codigo', 'marca', 'id_categoria');
                },
                'producto.categoria:id,nombre',
            ])
            ->whereHas('venta', function ($query) use ($request, $idEmpresa) {
                $query->where('enable', 1)
                    ->when($idEmpresa !== null, function ($q) use ($idEmpresa) {
                        $q->where('id_empresa', $idEmpresa);
                    })
                    ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($q) use ($request) {
                        $q->whereIn('id_sucursal', $request->sucursales);
                    })
                    ->when($request->inicio, function ($q) use ($request) {
                        return $q->where('fecha', '>=', $request->inicio);
                    })
                    ->when($request->fin, function ($q) use ($request) {
                        return $q->where('fecha', '<=', $request->fin);
                    })
                    ->when($request->id_sucursal, function ($q) use ($request) {
                        return $q->where('id_sucursal', $request->id_sucursal);
                    })
                    ->when($request->id_bodega, function ($q) use ($request) {
                        return $q->where('id_bodega', $request->id_bodega);
                    })
                    ->when($request->id_cliente, function ($q) use ($request) {
                        return $q->where('id_cliente', $request->id_cliente);
                    })
                    ->when($request->id_usuario, function ($q) use ($request) {
                        return $q->where('id_usuario', $request->id_usuario);
                    })
                    ->when($request->id_canal, function ($q) use ($request) {
                        return $q->whereHas('venta', function ($venta) use ($request) {
                            $venta->where('id_canal', $request->id_canal);
                        });
                    })
                    ->when($request->id_proyecto, function ($q) use ($request) {
                        return $q->whereHas('venta', function ($venta) use ($request) {
                            $venta->where('id_proyecto', $request->id_proyecto);
                        });
                    })
                    ->when($request->id_vendedor, function ($q) use ($request) {
                        return $q->whereHas('venta', function ($venta) use ($request) {
                            $venta->where('id_vendedor', $request->id_vendedor);
                        });
                    })
                    ->when($request->buscador, function ($q) use ($request) {
                        $buscador = '%' . $request->buscador . '%';
                        return $q->where(function ($inner) use ($buscador) {
                            $inner->whereHas('cliente', function ($qCliente) use ($buscador) {
                                $qCliente->where('nombre', 'like', $buscador)
                                    ->orWhere('nombre_empresa', 'like', $buscador)
                                    ->orWhere('ncr', 'like', $buscador)
                                    ->orWhere('nit', 'like', $buscador);
                            })
                                ->orWhere('correlativo', 'like', $buscador)
                                ->orWhere('observaciones', 'like', $buscador);
                        });
                    });
            });
    }

    public function map($row): array{
        if (DevolucionEnReporte::esDevolucion($row)) {
            return $this->mapDevolucionDetalle($row);
        }

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
            self::nombreCanalParaExport($venta),
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
            $row->vendedor
                ? $row->vendedor->name
                : (($venta && $venta->vendedor) ? $venta->vendedor->name : 'Sin vendedor'),
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
     * Etiqueta de canal para export: "Consigna" si alguna línea usa stock consigna_compra.
     */
    public static function nombreCanalParaExport(?object $venta): ?string
    {
        if (!$venta) {
            return null;
        }

        $detalles = $venta->relationLoaded('detalles')
            ? $venta->detalles
            : $venta->detalles()->get(['id', 'id_venta', 'origen_stock']);

        foreach ($detalles as $detalle) {
            if (OrigenStockVentaConstants::esConsignaCompra($detalle->origen_stock ?? null)) {
                return 'Consigna';
            }
        }

        return $venta->canal?->nombre;
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

    private function mapDevolucionDetalle(DetalleDevolucion $row): array
    {
        $devolucion = $row->venta;
        $ventaOrigen = ($devolucion && $devolucion->relationLoaded('venta')) ? $devolucion->venta : null;
        $documentoNombre = ($devolucion && $devolucion->documento) ? $devolucion->documento->nombre : 'Devolución';

        $ivaLinea = 0.0;
        if ($devolucion && (float) $devolucion->iva > 0 && (float) $devolucion->sub_total > 0 && (float) $row->total > 0) {
            $ivaLinea = ((float) $row->total / (float) $devolucion->sub_total) * (float) $devolucion->iva;
        }

        $montos = DevolucionEnReporte::montosDetalleNegados($row, $ivaLinea);
        $producto = $row->producto;
        $categoriaNombre = ($producto && $producto->relationLoaded('categoria') && $producto->categoria)
            ? $producto->categoria->nombre
            : '';
        $cliente = ($devolucion && $devolucion->cliente) ? $devolucion->cliente : null;

        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = $cliente->tipo == 'Empresa'
                ? (string) $cliente->nombre_empresa
                : trim($cliente->nombre . ' ' . $cliente->apellido);
        }

        $fields = [
            $devolucion ? $devolucion->fecha : null,
            $nombreCliente,
            $cliente ? $cliente->telefono : null,
            $cliente ? $cliente->dui : null,
            $cliente ? $cliente->nit : null,
            $producto ? $producto->nombre : ($row->descripcion ?? null),
            $producto ? $producto->codigo : null,
            $producto ? $producto->marca : null,
            $categoriaNombre,
            $documentoNombre,
            ($ventaOrigen && $ventaOrigen->relationLoaded('proyecto') && $ventaOrigen->proyecto)
                ? $ventaOrigen->proyecto->nombre
                : null,
            $ventaOrigen ? $ventaOrigen->num_identificacion : null,
            $devolucion ? $devolucion->correlativo : null,
            $ventaOrigen ? $ventaOrigen->forma_pago : null,
            $ventaOrigen ? $ventaOrigen->detalle_banco : null,
            DevolucionEnReporte::ESTADO,
            ($ventaOrigen && $ventaOrigen->canal) ? $ventaOrigen->canal->nombre : null,
            $montos['cantidad'],
            $montos['costo'],
            $montos['precio'],
            $montos['descuento'],
            $montos['iva'],
            $montos['utilidad'],
            $montos['total'],
            ($devolucion && $devolucion->sucursal && $devolucion->sucursal->empresa)
                ? $devolucion->sucursal->empresa->nombre
                : null,
            $devolucion ? $devolucion->observaciones : null,
            ($devolucion && $devolucion->usuario) ? $devolucion->usuario->name : null,
            ($ventaOrigen && $ventaOrigen->vendedor) ? $ventaOrigen->vendedor->name : 'Sin vendedor',
            ($devolucion && $devolucion->sucursal) ? $devolucion->sucursal->nombre : null,
        ];
        if ($this->incluirPaquetes) {
            $fields[] = '';
            $fields[] = '';
            $fields[] = '';
        }

        return $fields;
    }
}
