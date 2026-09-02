<?php

namespace App\Exports;

use App\Exports\Support\DevolucionEnReporte;
use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VentasExport implements FromCollection, WithHeadings, WithMapping
{
    public $request;

    public $tieneModuloPaquetes = false;

    public function __construct($request = null)
    {
        $this->request = $request;
        $this->tieneModuloPaquetes = $this->resolveModuloPaquetes($request);
    }

    public function filter(Request $request)
    {
        $this->request = $request;
        $this->tieneModuloPaquetes = $this->resolveModuloPaquetes($request);
    }

    private function resolveModuloPaquetes($request): bool
    {
        if (auth()->check()) {
            return (int) auth()->user()->empresa->modulo_paquetes === 1;
        }
        if ($request && $request->id_empresa) {
            $empresa = Empresa::query()->find($request->id_empresa);

            return $empresa && (int) $empresa->modulo_paquetes === 1;
        }

        return false;
    }

    /**
     * Año válido para filtrar la exportación (2023–2100), o null si no aplica / inválido.
     */
    public static function anioDesdeRequest(?Request $request): ?int
    {
        if (!$request) {
            return null;
        }
        $anio = is_numeric($request->anio ?? null) ? (int) $request->anio : null;
        if ($anio !== null && ($anio < 2023 || $anio > 2100)) {
            return null;
        }

        return $anio;
    }

    public static function motivoAnulacionParaExport($row): string
    {
        if (($row->estado ?? '') !== 'Anulada') {
            return '';
        }

        return (string) ($row->motivo_anulacion ?? '');
    }

    public function headings(): array
    {
        $empresa = $this->resolveEmpresa($this->request);
        $taxLabel = CountryTermsHelper::tax('taxLabel', $empresa);
        $totalWithoutTax = CountryTermsHelper::tax('totalWithoutTax', $empresa);

        $columnas = [
            'Fecha',
            'Cliente',
            'Telefono',
            'DUI',
            'NIT',
            'Dirección',
            'Documento',
            'Proyecto',
            'Num Identificacion',
            'Correlativo',
            'Forma de pago',
            'Banco',
            'Estado',
            'Motivo de Anulación',
            'Canal',
            'Costo',
            'Cuenta terceros',
            'Sub Total',
            'Descuento',
            $taxLabel,
            'Utilidad',
            $totalWithoutTax,
            'Total',
            'Propina',
            'Empresa',
            'Observaciones',
            'Usuario',
            'Vendedor',
        ];
        if ($this->tieneModuloPaquetes) {
            $columnas[] = 'WR';
            $columnas[] = 'Número de Guía';
            $columnas[] = 'Seguimiento';
        }

        return $columnas;
    }

    private function resolveEmpresa($request): ?Empresa
    {
        if (auth()->check()) {
            return auth()->user()->empresa;
        }
        if ($request && $request->id_empresa) {
            return Empresa::query()->find($request->id_empresa);
        }

        return null;
    }

    /**
     * Columnas mínimas de ventas para filtros, orden y map() (incluye FKs para relaciones).
     */
    private function columnasVentaSelect(): array
    {
        return [
            'id',
            'id_empresa',
            'id_cliente',
            'id_usuario',
            'id_vendedor',
            'id_sucursal',
            'id_canal',
            'id_proyecto',
            'id_documento',
            'id_bodega',
            'fecha',
            'fecha_pago',
            'created_at',
            'recurrente',
            'num_identificacion',
            'correlativo',
            'forma_pago',
            'detalle_banco',
            'estado',
            'motivo_anulacion',
            'sello_mh',
            'cotizacion',
            'observaciones',
            'sub_total',
            'descuento',
            'iva',
            'total_costo',
            'cuenta_a_terceros',
            'total',
            'propina',
        ];
    }

    public function collection(): Collection
    {
        $ventas = $this->query()->get();
        $devoluciones = $this->queryDevoluciones()->get()->each(function (Devolucion $devolucion) {
            DevolucionEnReporte::marcar($devolucion);
            if ($devolucion->relationLoaded('detalles')) {
                $devolucion->total_costo = $devolucion->detalles->sum(function ($detalle) {
                    return ((float) $detalle->cantidad) * ((float) $detalle->costo);
                });
            }
        });

        return $ventas->concat($devoluciones)
            ->sortByDesc(function ($row) {
                return sprintf('%s-%010d', (string) $row->fecha, (int) ($row->id ?? 0));
            })
            ->values();
    }

    /**
     * Query de ventas (también usado por VentasDesglosadasPorVendedorExport).
     */
    public function query()
    {
        $request = $this->request;
        if (!$request) {
            return Venta::query()->whereRaw('1 = 0');
        }

        $columnasOrdenPermitidas = ['id', 'fecha', 'correlativo', 'total', 'estado', 'created_at', 'num_identificacion', 'id_proyecto', 'fecha_pago', 'sub_total', 'iva', 'descuento'];
        $orden = in_array($request->orden ?? '', $columnasOrdenPermitidas) ? $request->orden : 'fecha';
        $direccion = in_array(strtolower($request->direccion ?? ''), ['asc', 'desc']) ? strtolower($request->direccion) : 'desc';

        $idEmpresaFiltro = null;
        if (auth()->check()) {
            $idEmpresaFiltro = auth()->user()->id_empresa;
        } elseif ($request->id_empresa) {
            $idEmpresaFiltro = (int) $request->id_empresa;
        }

        $anio = self::anioDesdeRequest($request);

        $relaciones = [
            'cliente:id,tipo,nombre,apellido,nombre_empresa,telefono,dui,nit,direccion',
            'usuario:id,name',
            'vendedor:id,name',
            'sucursal:id,nombre,id_empresa',
            'sucursal.empresa:id,nombre',
            'documento:id,nombre',
            'canal:id,nombre',
            'proyecto:id,nombre',
        ];
        if ($this->tieneModuloPaquetes) {
            $relaciones[] = 'paquetes:id,id_venta,wr,num_guia,num_seguimiento';
        }

        return Venta::query()
            ->select($this->columnasVentaSelect())
            ->with($relaciones)
            ->when($idEmpresaFiltro !== null, function ($query) use ($idEmpresaFiltro) {
                return $query->where('ventas.id_empresa', $idEmpresaFiltro);
            })
            ->when($anio !== null, function ($query) use ($anio) {
                return $query->whereYear('fecha', $anio);
            })
            ->when($anio === null && $request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($anio === null && $request->fin, function ($query) use ($request) {
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
            ->when(!empty($request->sucursales) && is_array($request->sucursales), function ($query) use ($request) {
                return $query->whereIn('ventas.id_sucursal', $request->sucursales);
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
                // Agrupado en subquery para evitar que rompa otros filtros
                return $query->where(function ($q) use ($request) {
                    $q->where('forma_pago', $request->forma_pago)
                        ->orWhereHas('metodos_de_pago', function ($sub) use ($request) {
                            $sub->where('nombre', $request->forma_pago);
                        });
                });
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                // Agrupado en subquery para evitar que rompa otros filtros
                return $query->where(function ($q) use ($request) {
                    $q->where('id_vendedor', $request->id_vendedor)
                        ->orWhereHas('detalles', function ($sub) use ($request) {
                            $sub->where('id_vendedor', $request->id_vendedor);
                        });
                });
            })
            ->when($request->id_canal, function ($query) use ($request) {
                return $query->where('id_canal', $request->id_canal);
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->id_documento, function ($query) use ($request) {
                // Eliminamos la consulta extra (find) para mayor eficiencia
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->estado, function ($query) use ($request) {
                return $query->where('estado', $request->estado);
            })
            ->when($request->metodo_pago, function ($query) use ($request) {
                // Agrupado en subquery
                return $query->where(function ($q) use ($request) {
                    $q->where('forma_pago', $request->metodo_pago)
                        ->orWhereHas('metodos_de_pago', function ($sub) use ($request) {
                            $sub->where('nombre', $request->metodo_pago);
                        });
                });
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
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc');
    }

    public function queryDevoluciones()
    {
        $request = $this->request;
        if (!$request) {
            return Devolucion::query()->whereRaw('1 = 0');
        }

        $idEmpresaFiltro = null;
        if (auth()->check()) {
            $idEmpresaFiltro = auth()->user()->id_empresa;
        } elseif ($request->id_empresa) {
            $idEmpresaFiltro = (int) $request->id_empresa;
        }

        $anio = self::anioDesdeRequest($request);

        return Devolucion::query()
            ->with([
                'cliente:id,tipo,nombre,apellido,nombre_empresa,telefono,dui,nit,direccion',
                'usuario:id,name',
                'sucursal:id,nombre,id_empresa',
                'sucursal.empresa:id,nombre',
                'documento:id,nombre',
                'detalles:id,id_devolucion_venta,cantidad,costo',
                'venta:id,id_canal,id_proyecto,id_vendedor,forma_pago,detalle_banco,num_identificacion',
                'venta.canal:id,nombre',
                'venta.proyecto:id,nombre',
                'venta.vendedor:id,name',
            ])
            ->where('enable', 1)
            ->when($idEmpresaFiltro !== null, function ($query) use ($idEmpresaFiltro) {
                return $query->where('id_empresa', $idEmpresaFiltro);
            })
            ->when($anio !== null, function ($query) use ($anio) {
                return $query->whereYear('fecha', $anio);
            })
            ->when($anio === null && $request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($anio === null && $request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
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
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_usuario, function ($query) use ($request) {
                return $query->where('id_usuario', $request->id_usuario);
            })
            ->when($request->id_canal, function ($query) use ($request) {
                return $query->whereHas('venta', function ($venta) use ($request) {
                    $venta->where('id_canal', $request->id_canal);
                });
            })
            ->when($request->id_proyecto, function ($query) use ($request) {
                return $query->whereHas('venta', function ($venta) use ($request) {
                    $venta->where('id_proyecto', $request->id_proyecto);
                });
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->whereHas('venta', function ($venta) use ($request) {
                    $venta->where('id_vendedor', $request->id_vendedor);
                });
            })
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
                        ->orWhere('observaciones', 'like', $buscador);
                });
            });
    }

    /**
     * Usa relaciones eager-loaded (sin queries adicionales).
     * Compatible con PHP 7.4 (sin el operador ?->).
     */
    public function map($row): array
    {
        if (DevolucionEnReporte::esDevolucion($row)) {
            return $this->mapDevolucion($row);
        }

        $cliente = $row->relationLoaded('cliente') ? $row->cliente : null;
        $sucursal = $row->relationLoaded('sucursal') ? $row->sucursal : null;
        $empresa = ($sucursal && $sucursal->relationLoaded('empresa')) ? $sucursal->empresa : null;
        $usuario = $row->relationLoaded('usuario') ? $row->usuario : null;
        $vendedor = $row->relationLoaded('vendedor') ? $row->vendedor : null;
        $documento = $row->relationLoaded('documento') ? $row->documento : null;
        $canal = $row->relationLoaded('canal') ? $row->canal : null;
        $proyecto = $row->relationLoaded('proyecto') ? $row->proyecto : null;

        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = ($cliente->tipo == 'Empresa')
                ? $cliente->nombre_empresa
                : trim($cliente->nombre . ' ' . $cliente->apellido);
        }

        $nombreUsuario = ($usuario && isset($usuario->name)) ? $usuario->name : '';
        $nombreVendedor = ($vendedor && isset($vendedor->name)) ? $vendedor->name : '';
        $nombreDocumento = ($documento && isset($documento->nombre)) ? $documento->nombre : '';
        $nombreCanal = ($canal && isset($canal->nombre)) ? $canal->nombre : '';
        $nombreProyecto = ($proyecto && isset($proyecto->nombre)) ? $proyecto->nombre : '';

        $cuentaTerceros = $row->cuenta_a_terceros !== null ? $row->cuenta_a_terceros : 0;
        $propina = $row->propina !== null ? $row->propina : 0;

        $fila = [
            $row->fecha,
            $nombreCliente,
            $cliente ? $cliente->telefono : '',
            $cliente ? $cliente->dui : '',
            $cliente ? $cliente->nit : '',
            $cliente ? $cliente->direccion : '',
            $nombreDocumento,
            $nombreProyecto,
            $row->num_identificacion,
            $row->correlativo,
            $row->forma_pago,
            $row->detalle_banco,
            $row->estado,
            self::motivoAnulacionParaExport($row),
            $nombreCanal,
            $row->estado == 'Anulada' ? '0.0' : round($row->total_costo, 2),
            round($cuentaTerceros, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->sub_total, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->descuento, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->iva, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->total - $row->total_costo - $row->iva, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->sub_total - $row->descuento, 2),
            $row->estado == 'Anulada' ? '0.0' : round($row->total, 2),
            $row->estado == 'Anulada' ? '0.0' : round($propina, 2),
            $empresa ? $empresa->nombre : '',
            $row->observaciones,
            $nombreUsuario,
            $nombreVendedor,
        ];

        if ($this->tieneModuloPaquetes) {
            $paquete = $row->relationLoaded('paquetes') ? $row->paquetes->first() : null;
            $fila[] = $paquete !== null ? ($paquete->wr ?? '') : '';
            $fila[] = $paquete !== null ? ($paquete->num_guia ?? '') : '';
            $fila[] = $paquete !== null ? ($paquete->num_seguimiento ?? '') : '';
        }

        return $fila;
    }

    private function mapDevolucion(Devolucion $row): array
    {
        $cliente = $row->relationLoaded('cliente') ? $row->cliente : null;
        $sucursal = $row->relationLoaded('sucursal') ? $row->sucursal : null;
        $empresa = ($sucursal && $sucursal->relationLoaded('empresa')) ? $sucursal->empresa : null;
        $usuario = $row->relationLoaded('usuario') ? $row->usuario : null;
        $documento = $row->relationLoaded('documento') ? $row->documento : null;
        $venta = $row->relationLoaded('venta') ? $row->venta : null;
        $vendedor = ($venta && $venta->relationLoaded('vendedor')) ? $venta->vendedor : null;
        $canal = ($venta && $venta->relationLoaded('canal')) ? $venta->canal : null;
        $proyecto = ($venta && $venta->relationLoaded('proyecto')) ? $venta->proyecto : null;

        $nombreCliente = 'Consumidor Final';
        if ($cliente) {
            $nombreCliente = ($cliente->tipo == 'Empresa')
                ? $cliente->nombre_empresa
                : trim($cliente->nombre . ' ' . $cliente->apellido);
        }

        $montos = DevolucionEnReporte::montosVentaNegados($row);

        $fila = [
            $row->fecha,
            $nombreCliente,
            $cliente ? $cliente->telefono : '',
            $cliente ? $cliente->dui : '',
            $cliente ? $cliente->nit : '',
            $cliente ? $cliente->direccion : '',
            ($documento && isset($documento->nombre)) ? $documento->nombre : 'Devolución',
            ($proyecto && isset($proyecto->nombre)) ? $proyecto->nombre : '',
            $venta ? $venta->num_identificacion : '',
            $row->correlativo,
            $venta ? $venta->forma_pago : '',
            $venta ? $venta->detalle_banco : '',
            DevolucionEnReporte::ESTADO,
            self::motivoAnulacionParaExport($row),
            ($canal && isset($canal->nombre)) ? $canal->nombre : '',
            $montos['costo'],
            $montos['cuenta_terceros'],
            $montos['sub_total'],
            $montos['descuento'],
            $montos['iva'],
            $montos['utilidad'],
            $montos['total_sin_iva'],
            $montos['total'],
            $montos['propina'],
            $empresa ? $empresa->nombre : '',
            $row->observaciones,
            ($usuario && isset($usuario->name)) ? $usuario->name : '',
            ($vendedor && isset($vendedor->name)) ? $vendedor->name : '',
        ];

        if ($this->tieneModuloPaquetes) {
            $fila[] = '';
            $fila[] = '';
            $fila[] = '';
        }

        return $fila;
    }
}