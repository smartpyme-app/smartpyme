<?php

namespace App\Http\Controllers\Api\Ventas;

use App\Http\Controllers\Controller;
use App\Exports\ReportesAutomaticos\EstadoFinancieroConsolidadoSucursales\EstadoFinancieroConsolidadoSucursalesExport;
use App\Exports\ReportesAutomaticos\DetalleVentasPorVendedor\DetalleVentasVendedorExport;
use App\Exports\ReportesAutomaticos\InventarioPorSucursal\InventarioExport;
use App\Exports\ReportesAutomaticos\VentasComprasPorMarcaProveedor\VentasComprasPorMarcaProveedorExport;
use App\Exports\VentasAcumuladoExport;
use App\Exceptions\FacturacionException;
use Illuminate\Http\Request;

use JWTAuth;
use Carbon\Carbon;
use App\Services\FidelizacionCliente\ConsumoPuntosService as FidelizacionConsumoPuntosService;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\DetalleCompuesto;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade as PDF;
use App\Http\Requests\Ventas\StoreVentaRequest;
use App\Http\Requests\Ventas\FacturacionRequest;
use App\Http\Requests\Ventas\IndexVentaRequest;
use App\Http\Requests\Ventas\LibroIvaRequest;
use App\Http\Requests\Ventas\HistorialVentaRequest;
use App\Services\Ventas\VentaService;
use App\Services\Ventas\InventarioService;
use App\Services\Ventas\VentaQueryService;
use App\Services\Ventas\AbonoService;
use App\Services\Ventas\DocumentoService;
use App\Services\Ventas\ReporteService;
use App\Services\Ventas\FacturacionConsignaService;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\LibroIvaService;
use App\Services\Ventas\CorteService;
use App\Services\Ventas\CxcService;
use App\Services\Ventas\HistorialService;
use App\Services\Ventas\ReporteEmailService;
use App\Services\Ventas\CotizacionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Empresa;
use App\Models\Admin\Caja;
use App\Models\Admin\Documento;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\MetodoDePago;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Paquete;
use App\Services\Webhooks\WebhookPaqueteVentaDispatcher;
use App\Models\Contabilidad\Proyecto;
use App\Models\Eventos\Evento;
use App\Models\Restaurante\PedidoRestaurante;
use App\Services\Restaurante\PedidoCanalInventarioService;
use App\Services\Inventario\ConversionInventarioService;
use App\Services\Inventario\LoteAsignacionService;
use App\Services\Inventario\StockDisponibleService;
use Luecano\NumeroALetras\NumeroALetras;
use Illuminate\Support\Facades\Schema;
use App\Exports\VentasExport;
use App\Exports\VentasDesglosadasPorVendedorExport;
use App\Exports\VentasDetallesExport;
use App\Helpers\ExportPeriodHelper;
use App\Exports\ReportesAutomaticos\VentasPorCategoriaPorVendedor\VentasPorCategoriaVendedorExport;
use App\Exports\ReportesAutomaticos\VentasPorVendedor\VentasPorVendedorExport;
use App\Exports\VentasPorUtilidadesExport;
use App\Exports\VentasPorMarcasExport;
use App\Exports\CobrosPorVendedorExport;
use App\Exports\CuentasCobrarExport;
use App\Mail\ReporteVentasPorVendedor;
use Maatwebsite\Excel\Facades\Excel;

class VentasController extends Controller
{
    protected $ventaService;
    protected $inventarioService;
    protected $ventaQueryService;
    protected $abonoService;
    protected $documentoService;
    protected $reporteService;
    protected $facturacionConsignaService;
    protected $libroIvaService;
    protected $corteService;
    protected $cxcService;
    protected $historialService;
    protected $reporteEmailService;
    protected $cotizacionService;
    protected $facturacionService;

    public function __construct(
        VentaService $ventaService,
        InventarioService $inventarioService,
        VentaQueryService $ventaQueryService,
        AbonoService $abonoService,
        DocumentoService $documentoService,
        ReporteService $reporteService,
        FacturacionConsignaService $facturacionConsignaService,
        FacturacionService $facturacionService,
        LibroIvaService $libroIvaService,
        CorteService $corteService,
        CxcService $cxcService,
        HistorialService $historialService,
        ReporteEmailService $reporteEmailService,
        CotizacionService $cotizacionService
    ) {
        $this->ventaService = $ventaService;
        $this->inventarioService = $inventarioService;
        $this->ventaQueryService = $ventaQueryService;
        $this->abonoService = $abonoService;
        $this->documentoService = $documentoService;
        $this->reporteService = $reporteService;
        $this->facturacionConsignaService = $facturacionConsignaService;
        $this->libroIvaService = $libroIvaService;
        $this->corteService = $corteService;
        $this->cxcService = $cxcService;
        $this->historialService = $historialService;
        $this->reporteEmailService = $reporteEmailService;
        $this->cotizacionService = $cotizacionService;
        $this->facturacionService = $facturacionService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $excludeFromList = ['dte_invalidacion'];
        $columns = array_values(array_diff(Schema::getColumnListing('ventas'), $excludeFromList));
        $dteIndex = array_search('dte', $columns, true);
        if ($dteIndex !== false) {
            $columns[$dteIndex] = DB::raw("IF(COALESCE(ventas.dte_s3_key,'') <> '', NULL, ventas.dte) as dte");
        }
        $columns[] = DB::raw("(CASE WHEN COALESCE(ventas.dte_invalidacion,'') <> '' OR COALESCE(ventas.dte_invalidacion_s3_key,'') <> '' THEN 1 ELSE 0 END) as tiene_dte_invalidacion");

        $ventas = Venta::select($columns)
            ->where('ventas.id_empresa', $user->id_empresa)
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
            ->when($request->id_documento, function ($query) use ($request) {
                $documento = Documento::find($request->id_documento);
                if ($documento) {
                    return $query->whereHas('documento', function ($q) use ($documento) {
                        $q->whereRaw('LOWER(nombre) = LOWER(?)', [$documento->nombre]);
                    });
                }
                return $query->where('id_documento', $request->id_documento);
            })
            ->when($request->dte && $request->dte == 1, function ($query) {
                return $query->whereNull('sello_mh');
            })
            ->when($request->dte && $request->dte == 2, function ($query) {
                return $query->whereNotNull('sello_mh');
            })
            ->where('cotizacion', 0)
            ->when($request->buscador, fn ($query) => $this->aplicarFiltroBuscador($query, (string) $request->buscador))
            ->with(['cliente', 'usuario', 'vendedor', 'sucursal', 'canal', 'documento', 'proyecto'])
            ->with(['devolucionesNcNd' => function ($query) {
                $query->with('documento:id,nombre');
            }])
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        return Response()->json($ventas, 200);
    }

    /**
     * Aplica el filtro de búsqueda por cliente (FULLTEXT: nombre, apellido, ncr, nit, nombre_empresa),
     * correlativo (LIKE), y ventas (FULLTEXT: num_orden, observaciones, forma_pago, estado, numero_control).
     */
    private function aplicarFiltroBuscador($query, string $termino)
    {
        $termino = trim(preg_replace('/\s+/', ' ', $termino));
        if ($termino === '') {
            return $query;
        }

        $buscador = '%' . $termino . '%';
        $palabras = array_values(array_filter(explode(' ', $termino), fn ($p) => $p !== ''));

        $matchClientes = count($palabras) > 1
            ? implode(' ', array_map(fn ($p) => '+' . preg_replace('/[+\-<>()~*"]/', '', $p), $palabras))
            : $termino;
        $modoClientes = count($palabras) > 1 ? 'BOOLEAN' : 'NATURAL LANGUAGE';

        $clienteIds = Cliente::query()
            ->whereRaw(
                'MATCH(clientes.nombre, clientes.apellido, clientes.nombre_empresa, clientes.nit, clientes.ncr) AGAINST(? IN ' . $modoClientes . ' MODE)',
                [$matchClientes]
            )
            ->limit(5000)
            ->pluck('id');

        // FULLTEXT en BOOLEAN exige tokens que no coinciden con razones sociales (S.A, C.V, stopwords como DE).
        if (count($palabras) > 1) {
            $idsLike = Cliente::query()
                ->where(function ($q) use ($buscador) {
                    $q->where('nombre_empresa', 'like', $buscador)
                        ->orWhere('nombre', 'like', $buscador)
                        ->orWhere('apellido', 'like', $buscador)
                        ->orWhere('nit', 'like', $buscador)
                        ->orWhere('ncr', 'like', $buscador)
                        ->orWhereRaw("CONCAT(TRIM(nombre), ' ', TRIM(apellido)) LIKE ?", [$buscador]);
                })
                ->limit(5000)
                ->pluck('id');
            $clienteIds = $clienteIds->merge($idsLike)->unique()->values()->take(5000);
        }

        return $query->where(function ($q) use ($buscador, $clienteIds, $termino) {
            if ($clienteIds->isNotEmpty()) {
                $q->whereIn('id_cliente', $clienteIds);
            }
            $q->orWhere('correlativo', 'like', $buscador)
                ->orWhereRaw(
                    'MATCH(ventas.num_orden, ventas.observaciones, ventas.forma_pago, ventas.estado, ventas.numero_control) AGAINST(? IN NATURAL LANGUAGE MODE)',
                    [$termino]
                );
        });
    }

    public function read($id)
    {
        $venta = Venta::where('id', $id)
            ->with('devoluciones', 'detalles.composiciones', 'detalles.vendedor', 'detalles.producto.impuestos', 'abonos.usuario', 'cliente', 'impuestos.impuesto', 'metodos_de_pago')
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->first();

        if (!$venta) {
            return response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
        }

        $venta->saldo = round($venta->total - ($venta->abonos_sum_total ?? 0) - ($venta->devoluciones_sum_total ?? 0), 2);
        return Response()->json($venta, 200);
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'fecha'             => 'required',
    //         'estado'            => 'required',
    //         'id_usuario'        => 'required',
    //     ]);


    //     $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();

    //     // Ajustar stocks
    //     foreach ($venta->detalles as $detalle) {

    //         $producto = Producto::where('id', $detalle->id_producto)
    //             ->with('composiciones')->firstOrFail();

    //         $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();

    //         // Anular venta y regresar stock
    //         if (($venta->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

    //             if ($inventario) {
    //                 $inventario->stock += $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad * -1);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_producto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock += $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad) * -1);
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Cancelado';
    //                 $abono->save();
    //             }

    //             if ($inventario) {
    //                 $inventario->stock += $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad * -1);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_compuesto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock += $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, $detalle->cantidad);
    //                 }

    //                 // Inventario compuestos
    //                 foreach ($detalle->composiciones()->get() as $comp) {

    //                     $inventario = Inventario::where('id_producto', $comp->id_producto)
    //                         ->where('id_bodega', $venta->id_bodega)->first();

    //                     if ($inventario) {
    //                         $inventario->stock -= $detalle->cantidad * $comp->cantidad;
    //                         $inventario->save();
    //                         $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
    //                     }
    //                 }

    //                 // Abonos
    //                 foreach ($venta->abonos as $abono) {
    //                     $abono->estado = 'Confirmado';
    //                     $abono->save();
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Cancelado';
    //                 $abono->save();
    //             }
    //         }
    //         // Cancelar anulación de venta y descargar stock
    //         if (($venta->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
    //             // Aplicar stock
    //             if ($inventario) {
    //                 $inventario->stock -= $detalle->cantidad;
    //                 $inventario->save();
    //                 $inventario->kardex($venta, $detalle->cantidad);
    //             }

    //             // Inventario compuestos
    //             foreach ($detalle->composiciones()->get() as $comp) {

    //                 $inventario = Inventario::where('id_producto', $comp->id_compuesto)
    //                     ->where('id_bodega', $venta->id_bodega)->first();

    //                 if ($inventario) {
    //                     $inventario->stock -= $detalle->cantidad * $comp->cantidad;
    //                     $inventario->save();
    //                     $inventario->kardex($venta, ($detalle->cantidad * $comp->cantidad));
    //                 }
    //             }

    //             // Abonos
    //             foreach ($venta->abonos as $abono) {
    //                 $abono->estado = 'Confirmado';
    //                 $abono->save();
    //             }
    //         }
    //     }

    //     $venta->fill($request->all());
    //     $venta->save();

    //     return Response()->json($venta, 200);
    // }


    public function store(Request $request)
    {
        $request->validate([
            'id'                => 'required|numeric',
            'fecha'             => 'required',
            'estado'            => 'required',
            'id_usuario'        => 'required',
        ]);

        // Buscar la venta respetando el scope global de empresa
        $venta = Venta::where('id', $request->id)->with('detalles')->first();

        if (!$venta) {
            return response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
        }

        $webhookPaquetesFacturadosBulk = false;

        // Ajustar stocks
        foreach ($venta->detalles as $detalle) {

            // ── Resolución del factor de conversión (presentaciones) ──────────
            $factorPresentacion = 1;
            if (!empty($detalle->id_presentacion)) {
                $presentacion = \App\Models\Inventario\ProductoPresentacion::find($detalle->id_presentacion);
                if ($presentacion) {
                    $factorPresentacion = (float) $presentacion->factor_conversion;
                }
            }
            // Cantidad en unidades base que se mueve en inventario/Kardex
            $cantidadBase = ConversionInventarioService::calcularCantidadBase(
                $detalle->cantidad,
                $factorPresentacion
            );

            $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $venta->id_bodega)->first();

            // Anular venta y regresar stock
            if (($venta->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

                if ($inventario) {
                    LoteAsignacionService::revertirEntrada($detalle, $venta, $inventario, $cantidadBase);
                }

                // Inventario compuestos (los compuestos no usan presentaciones: su factor es siempre 1)
                foreach ($detalle->composiciones()->get() as $comp) {
                    $inventario = Inventario::where('id_producto', $comp->id_producto)
                        ->where('id_bodega', $venta->id_bodega)->first();

                    if ($inventario) {
                        $cantidadCompBase = ConversionInventarioService::calcularCantidadBase(
                            $detalle->cantidad * $comp->cantidad,
                            1
                        );
                        $inventario->stock += $cantidadCompBase;
                        $inventario->save();
                        $inventario->kardex($venta, $cantidadCompBase * -1);
                    }
                }

                // Abonos
                foreach ($venta->abonos as $abono) {
                    $abono->estado = 'Cancelado';
                    $abono->save();
                }

                // Paquetes de la venta: cambiar estado a En bodega
                Paquete::where('id_venta', $venta->id)->update(['estado' => 'En bodega']);
            }
            // Cancelar anulación de venta y descargar stock
            if (($venta->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
                if ($inventario) {
                    LoteAsignacionService::reactivarSalidaDesdeDetalle(
                        $detalle,
                        $venta,
                        $inventario,
                        $cantidadBase,
                        (float) ($detalle->precio ?? 0)
                    );
                }

                // Inventario compuestos
                foreach ($detalle->composiciones()->get() as $comp) {
                    $inventario = Inventario::where('id_producto', $comp->id_producto)
                        ->where('id_bodega', $venta->id_bodega)->first();

                    if ($inventario) {
                        $cantidadCompBase = ConversionInventarioService::calcularCantidadBase(
                            $detalle->cantidad * $comp->cantidad,
                            1
                        );
                        $inventario->stock -= $cantidadCompBase;
                        $inventario->save();
                        $inventario->kardex($venta, $cantidadCompBase);
                    }
                }

                // Abonos
                foreach ($venta->abonos as $abono) {
                    $abono->estado = 'Confirmado';
                    $abono->save();
                }
                // Paquetes de la venta: al revertir anulación quedan Facturados (update masivo no dispara observer)
                Paquete::where('id_venta', $venta->id)->update(['estado' => 'Facturado']);
                if (!$webhookPaquetesFacturadosBulk) {
                    $webhookPaquetesFacturadosBulk = true;
                    $idsPaquetes = Paquete::withoutGlobalScopes()
                        ->where('id_venta', $venta->id)
                        ->pluck('id');
                    foreach ($idsPaquetes as $pid) {
                        WebhookPaqueteVentaDispatcher::dispatch((int) $pid);
                    }
                }
            }
        }

        // El frontend ya envía el total sin propina, así que no necesitamos ajustarlo
        $venta->fill($request->all());
        $venta->save();

        return Response()->json($venta, 200);
    }

    public function delete($id)
    {
        $venta = Venta::findOrFail($id);

        foreach ($venta->detalles as $detalle) {
            $detalle->delete();
        }
        $venta->delete();

        return Response()->json($venta, 201);
    }

    // Facturacion

    public function corte()
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $caja   = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte  = $caja->corte;
        $ventas = $corte->ventas()->orderBy('id', 'desc')
            ->paginate(30);

        return Response()->json($ventas, 200);
    }

    public function facturacion(FacturacionRequest $request)
    {
        $user = auth()->user();

        try {
            $this->facturacionService->assertReglasNegocio($user, $request);

            $venta = $this->facturacionService->procesar($user, $request);

            return Response()->json($venta, 200);
        } catch (FacturacionException $e) {
            return Response()->json(['error' => $e->getMessage()], $e->httpStatus);
        }
    }

    public function facturacionConsigna(Request $request)
    {
        // Validar que usuarios "Ventas Limitado" no puedan crear ventas al crédito
        $user = auth()->user();
        if ($user->tipo === 'Ventas Limitado') {
            return response()->json([
                'error' => 'Los usuarios de tipo "Ventas Limitado" no pueden crear ventas al crédito.'
            ], 403);
        }

        $request->validate([
            'id'                => 'required',
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            'id_documento'      => 'required|max:255',
            'id_canal'          => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            'fecha_pago'        => 'required',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
        ], [
            'detalles.required' => 'Tiene que agregar productos a la venta',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
        ]);

        DB::beginTransaction();

        try {
            $venta = Venta::where('id', $request->id)->with('detalles')->firstOrFail();
            if (round($venta->total, 2) > round($request->total, 2)) {
                // Crear consigna
                $consigna = new Venta();
                $consigna->fill($request->all());
                $consigna->estado = 'Consigna';
                $consigna->sub_total = $venta->sub_total - $request->sub_total;
                $consigna->total_costo  = $venta->total_costo  - $request->total_costo;
                $consigna->total = $venta->total - $request->total;
                $consigna->iva = $venta->iva - $request->iva;
                $consigna->save();

                foreach ($request->detalles as $detalle) {

                    $detalle_venta = $venta->detalles()->where('id', $detalle['id'])->first();
                    if ($detalle_venta) {
                        if ($detalle_venta->cantidad > $detalle['cantidad']) {
                            $detalle_consigna = new Detalle();
                            $detalle_consigna->id_producto = $detalle['id_producto'];
                            $detalle_consigna->precio = $detalle['precio'];
                            $detalle_consigna->cantidad = $detalle_venta->cantidad - $detalle['cantidad'];
                            $detalle_consigna->total = $detalle_consigna->precio * $detalle_consigna->cantidad;
                            $detalle_consigna->id_venta = $consigna->id;
                            $detalle_consigna->save();
                        }
                    }
                }

                //Guardar nuevos detalles
                $venta->detalles()->delete();

                foreach ($request->detalles as $detalle) {
                    if ($detalle['cantidad'] > 0) {
                        $det = new Detalle();
                        $det->id_producto = $detalle['id_producto'];
                        $det->cantidad = $detalle['cantidad'];
                        $det->precio = $detalle['precio'];
                        $det->total = $detalle['cantidad'] * $detalle['precio'];
                        $det->descuento = 0;
                        $det->id_venta = $venta->id;
                        $det->save();
                    }
                }

                $venta->total = $request->total;
                $venta->iva = $request->iva;
                $venta->sub_total = $request->sub_total;
            }


            $venta->fecha = $request->fecha;
            $venta->estado = 'Pagada';
            $venta->save();

            // Procesar puntos de fidelización si la venta está pagada
            if ($venta->id_cliente) {
                try {
                    $consumoPuntosService = app(FidelizacionConsumoPuntosService::class);
                    $consumoPuntosService->procesarAcumulacionPuntos($venta);
                } catch (\Exception $e) {
                    Log::error('Error al procesar puntos de fidelización al pagar venta', [
                        'venta_id' => $venta->id,
                        'error' => $e->getMessage()
                    ]);
                    // No se interrumpe la transacción por errores en puntos
                }
            }

            DB::commit();
            return Response()->json($venta, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function pendientes()
    {

        $usuario = JWTAuth::parseToken()->authenticate();

        $caja    = Caja::where('id', $usuario->id_caja)->with('corte')->firstOrFail();
        $corte   = $caja->corte;

        if ($corte) {
            if (!$corte->cierre)
                $corte->cierre = Carbon::now()->toDateTimeString();;

            $ventas  = $corte->ventas()->where('estado', 'En Proceso')
                ->orderBy('id', 'desc')
                ->paginate(5000);
        } else {
            $user = auth()->user();
            if (!$user || !$user->id_empresa) {
                return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
            }
            $ventas  = Venta::where('ventas.id_empresa', $user->id_empresa)
                ->where('estado', 'En Proceso')
                ->orderBy('id', 'desc')
                ->paginate(5000);
        }


        return Response()->json($ventas, 200);
    }

    public function vendedor()
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        if (!$usuario || !$usuario->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $ventas  = Venta::where('ventas.id_empresa', $usuario->id_empresa)
            ->where('estado', 'En Proceso')
            ->where('id_usuario', $usuario->id)
            ->orderBy('id', 'desc')
            ->paginate(5000);

        return Response()->json($ventas, 200);
    }

    public function generarDoc($id)
    {

        $venta = Venta::where('id', $id)->with('detalles', 'empresa')->firstOrFail();
        $documento = Documento::findOrfail($venta->id_documento);

        if ($documento->nombre == 'Ticket' || $documento->nombre == 'Recibo') {
            $documento = Documento::findOrfail($venta->id_documento);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            if (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            ) {
                $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $venta->pdf = false;
                return view(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
            }

            return view('reportes.facturacion.ticket', compact('venta', 'empresa', 'documento'));
        }

        if ($documento->nombre == 'Factura') {
            $cliente = Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            // Accesorios HN (716) o flag en custom_empresa
            if (
                (isset($empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn']) &&
                    $empresa->custom_empresa['configuraciones']['factura_ticket_accesorios_hn'] == true)
                || Auth::user()->id_empresa == 716
            ) {
                $venta->load('detalles.producto');
                $formatter = new NumeroALetras();
                $n = explode('.', number_format((float) $venta->total, 2, '.', ''));
                $dolares = $formatter->toWords((float) $n[0]);
                $centavosNum = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $venta->pdf = true;
                $pdf = app('dompdf.wrapper')->loadView(
                    'reportes.facturacion.formatos_empresas.Factura-Accesorios-HN-Ticket',
                    compact('venta', 'empresa', 'documento', 'cliente', 'dolares', 'centavosNum')
                );
                $alto_base = 300;
                $alto_por_producto = 24;
                $total_lineas = max(1, $venta->detalles->count());
                $notaExtra = $documento->nota ? min(45, (substr_count((string) $documento->nota, "\n") + 1) * 5) : 0;
                $alto_total_mm = $alto_base + ($total_lineas * $alto_por_producto) + $notaExtra;
                $alto_total_pt = $alto_total_mm * 2.83465;
                $ancho_pt = 80 * 2.83465;
                $pdf->setPaper([0, 0, $ancho_pt, $alto_total_pt]);
                return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
            }

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total, 2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
            $centavos = $formatter->toWords($n[1]);

            //return response()->json($n);

            if (Auth::user()->id_empresa == 38) { //38
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.velo', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 212) { //212
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.fotopro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 62) { //62
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.hotel-eco', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 84) { //84
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.devetsa', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 75) { //75
                // return View('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Biovet', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 104) { //104
                // return View('reportes.facturacion.formatos_empresas.Factura-coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.factura-Coloretes', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 11) { //11
                // return View('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-organika', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            } elseif (Auth::user()->id_empresa == 12) { //12
                // return View('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Ayakahuite', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 566.929133858]);
            } elseif (Auth::user()->id_empresa == 128) { //128
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.kiero-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283.46, 765.35]);
            } elseif (Auth::user()->id_empresa == 135) { //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Dentalkey-factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            } elseif (Auth::user()->id_empresa == 136) { //136 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Emerson', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 365.669, 609.4488]);
            } elseif (Auth::user()->id_empresa == 149) { //149 OK V2
                return View('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Natura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 187) { //187  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 24) { //24  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Via-del-Mar', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 174) { //174  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Consultora-Raices', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 59) { //59  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Smartpyme', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 420) { //420 Inversiones Andre - Honduras
                $venta->load('detalles.producto');
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Inversiones-Andre', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 614) { //614 Demo SP 2 - Honduras
                $venta->load('detalles.producto');
                $centavos = str_pad(isset($n[1]) ? $n[1] : '00', 2, '0', STR_PAD_LEFT);
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Factura-Accesorios-Honduras', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento'));
                $pdf->setPaper('US Letter', 'portrait');
            } else {
                // return View('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.factura', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos', 'documento'));
                $pdf->setPaper('US Letter', 'portrait');
            }


            return $pdf->stream($empresa->nombre . '-factura-' . $venta->correlativo . '.pdf');
        }

        if ($documento->nombre == 'Crédito fiscal') {
            $cliente = Cliente::withoutGlobalScope('empresa')->findOrfail($venta->id_cliente);

            $empresa = Empresa::findOrfail(Auth::user()->id_empresa);

            $formatter = new NumeroALetras();
            $n = explode(".", number_format($venta->total, 2));


            $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
            $centavos = $formatter->toWords($n[1]);

            if (Auth::user()->id_empresa == 24) { //24
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.vetvia-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 212) { //212
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-FotoPro', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 38) { //38
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.velo-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 62) { //62
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.hotel-eco-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 128) { //128
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.kiero-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 283, 765]);
            } elseif (Auth::user()->id_empresa == 135) { //135
                // return View('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Dentalkey-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 609.45, 467.72]);
            } elseif (Auth::user()->id_empresa == 136) { //136
                // return View('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.destroyesa-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper([0, 0, 297.64, 382.68]);
            } elseif (Auth::user()->id_empresa == 158) { //158
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.Guaca-Mix-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-Credicash', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 187) { //187  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-Express-Shopping', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 177) { //177  OK V2
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.CCF-TecnoGadget', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('Legal', 'portrait');
            } elseif (Auth::user()->id_empresa == 84) { //84
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.devetsa-cff', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } elseif (Auth::user()->id_empresa == 59) { //59
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.smartpyme-ccf', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            } else {
                $pdf = app('dompdf.wrapper')->loadView('reportes.facturacion.formatos_empresas.credito', compact('venta', 'empresa', 'cliente', 'dolares', 'centavos'));
                $pdf->setPaper('US Letter', 'portrait');
            }

            return $pdf->stream($empresa->nombre . '-credito-' . $venta->correlativo . '.pdf');
        }
    }

    public function anularDoc()
    {
        return view('reportes.anulacion');
    }

    public function sinDevolucion()
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $ventas = Venta::where('ventas.id_empresa', $user->id_empresa)
            ->where('estado', '!=', 'Anulada')
            ->where(function ($query) {
                // Obtener la fecha límite (hace dos meses desde ahora)
                $fechaInicio = Carbon::now()->subMonths(2)->startOfMonth();
                $fechaFin = Carbon::now()->endOfMonth();

                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            })
            ->whereHas('documento', function ($q) {
                $q->whereIn('nombre', ['Factura', 'Crédito fiscal']);
            })
            ->whereDoesntHave('devoluciones')
            ->orderBy('fecha', 'DESC')
            ->get();

        return Response()->json($ventas, 200);
    }

    public function libroIva(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $star = $request->inicio;
        $end = $request->fin;

        $ventas = Venta::with('cliente')
            ->where('ventas.id_empresa', $user->id_empresa)
            ->where('estado', '!=', 'Pendiente')
            ->when($request->tipo_documento, function ($query) use ($request) {
                return $query->whereHas('documento', function ($q) use ($request) {
                    $q->where('nombre', $request->tipo_documento);
                });
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderBy('fecha', 'desc')->get();

        $ivas = collect();

        foreach ($ventas as $venta) {
            $ivas->push([
                'fecha'                 => $venta->fecha,
                'clase_documento'       => 1,
                'tipo_documento'        => '03',
                'num_resolucion'        => $venta->documento()->pluck('resolucion')->first(),
                'num_serie'             => $venta->documento()->pluck('numero_autorizacion')->first(),
                'num_documento'         => $venta->correlativo,
                'num_control_interno'   => $venta->correlativo,
                'nit_nrc'               => $venta->cliente()->pluck('nit')->first() ? $venta->cliente()->pluck('nit')->first() : $venta->cliente()->pluck('ncr')->first(),
                'nombre_cliente'        => $venta->nombre_cliente,
                'ventas_exentas'        => $venta->exenta,
                'ventas_no_sujetas'     => $venta->no_sujeta,
                'ventas_gravadas'       => $venta->sub_total,
                'cuenta_a_terceros'     => $venta->cuenta_a_terceros,
                'debito_fiscal'         => $venta->iva,
                'ventas_cuenta_terceros' => 0,
                'debito_cuenta_terceros' => 0,
                'total'                 => $venta->total,
                'dui'                   => $venta->cliente()->pluck('dui')->first(),
                'num_anexto'            => 1,
            ]);
        }

        $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);
    }

    public function cxc(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $paginate = $request->paginate ?? 10;
        $orden = $request->orden ?? 'fecha';
        $direccion = $request->direccion ?? 'desc';

        $cobros = Venta::where('ventas.id_empresa', $user->id_empresa)
            ->where('estado', 'Pendiente')
            ->when($request->inicio, function ($query) use ($request) {
                return $query->where('fecha', '>=', $request->inicio);
            })
            ->when($request->fin, function ($query) use ($request) {
                return $query->where('fecha', '<=', $request->fin);
            })
            ->when($request->id_cliente, function ($query) use ($request) {
                return $query->where('id_cliente', $request->id_cliente);
            })
            ->when($request->id_vendedor, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('id_vendedor', $request->id_vendedor)
                        ->orWhereHas('detalles', function ($q2) use ($request) {
                            $q2->where('id_vendedor', $request->id_vendedor);
                        });
                });
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
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
                        ->orWhere('estado', 'like', $buscador)
                        ->orWhere('observaciones', 'like', $buscador);
                });
            })
            ->where('cotizacion', 0)
            ->with(['cliente'])
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc')
            ->paginate($paginate);

        return Response()->json($cobros, 200);
    }

    public function cxcExport(Request $request)
    {
        try {
            ini_set('memory_limit', '256M');
            set_time_limit(120);
            $export = new CuentasCobrarExport();
            $export->filter($request);
            return Excel::download($export, 'cuentas-por-cobrar.xlsx');
        } catch (\Throwable $e) {
            \Log::error('CXC Export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Error al generar el reporte: ' . $e->getMessage()], 500);
        }
    }

    public function cxcBuscar($txt)
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $cobros = Venta::where('ventas.id_empresa', $user->id_empresa)
            ->where('estado', 'Pendiente')
            ->whereHas('cliente', function ($query) use ($txt) {
                $query->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($cobros, 200);
    }

    public function historial(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $ventas = Venta::where('ventas.id_empresa', $user->id_empresa)
            ->where('estado', 'Pagada')
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->fecha)->format('d-m-Y');
            });

        $movimientos = collect();

        foreach ($ventas as $venta) {
            $ventaTotal = $venta->sum('total');
            $costoTotal = $venta->sum('subcosto');
            $movimientos->push([
                'cantidad'      => $venta->count(),
                'fecha'         => $venta[0]->fecha,
                'total'         => $ventaTotal,
                'costo'         => $costoTotal,
                'utilidad'      => $ventaTotal - $costoTotal,
                'detalles'      => $venta
            ]);
        }

        return Response()->json($movimientos, 200);
    }

    public function export(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_VENTAS_TOTALES,
            function () use ($request) {
                $ventas = new VentasExport();
                $ventas->filter($request);

                $anio = VentasExport::anioDesdeRequest($request);
                $nombreArchivo = $anio !== null ? "ventas-{$anio}.xlsx" : 'ventas.xlsx';

                return [$ventas, $nombreArchivo];
            }
        );
    }

    public function exportDetalles(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_DETALLES,
            function () use ($request) {
                $ventas = new VentasDetallesExport();
                $ventas->filter($request);

                return [$ventas, 'ventas-detalles.xlsx'];
            }
        );
    }

    /**
     * Descarga Excel de ventas con validación de período, memoria y tiempo de ejecución.
     *
     * @param  callable(): array{0: object, 1: string}  $exportFactory
     */
    private function downloadVentasExcel(Request $request, int $maxDias, callable $exportFactory)
    {
        ExportPeriodHelper::assertValidPeriod($request, $maxDias);

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            [$export, $nombreArchivo] = $exportFactory();

            return Excel::download($export, $nombreArchivo);
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            \Log::error('Error al exportar ventas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error al generar el reporte. Intente con un rango de fechas más corto.',
            ], 500);
        }
    }


    /**
     * Genera el reporte diario de ventas por vendedor
     *
     * @param Request $request Solicitud HTTP
     * @return mixed Descarga del archivo Excel o ruta del archivo generado
     * @throws \Exception Si ocurre un error al generar el reporte
     */
    public function reporteDiario(Request $request)
    {
        try {
            $fecha = Carbon::today()->format('Y-m-d');
            $export = new VentasPorVendedorExport($fecha);


            if ($request->has('enviar_correo')) {

                $reportDirectory = storage_path("app/public/reportes");
                $filename = "ventas-por-vendedor-{$fecha}.xlsx";
                $path = "{$reportDirectory}/{$filename}";


                if (!file_exists($reportDirectory)) {
                    if (!mkdir($reportDirectory, 0755, true)) {
                        throw new \Exception("No se pudo crear el directorio para los reportes");
                    }
                }

                Excel::store($export, "public/reportes/{$filename}");

                if (!file_exists($path)) {
                    throw new \Exception("El archivo del reporte no se pudo generar correctamente");
                }

                return $path;
            } else {
                return Excel::download(
                    $export,
                    "ventas-por-vendedor-{$fecha}.xlsx"
                );
            }
        } catch (\Exception $e) {
            Log::error("Error al generar reporte diario: " . $e->getMessage());

            throw $e;
        }
    }

    public function acumuladoExport(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_GENERAL,
            function () use ($request) {
                $user = JWTAuth::parseToken()->authenticate();
                $request->request->add(['id_empresa' => $user->id_empresa]);
                $ventas = new VentasAcumuladoExport();
                $ventas->filter($request);

                return [$ventas, 'corte.xlsx'];
            }
        );
    }

    public function porMarcasExport(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_GENERAL,
            function () use ($request) {
                $ventas = new VentasPorMarcasExport();
                $ventas->filter($request);

                return [$ventas, 'ventas-por-marcas.xlsx'];
            }
        );
    }

    public function porUtilidadesExport(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_GENERAL,
            function () use ($request) {
                $ventas = new VentasPorUtilidadesExport();
                $ventas->filter($request);

                return [$ventas, 'ventas-por-utilidades.xlsx'];
            }
        );
    }

    public function cobrosPorVendedorExport(Request $request)
    {
        return $this->downloadVentasExcel(
            $request,
            ExportPeriodHelper::MAX_DIAS_GENERAL,
            function () use ($request) {
                $cobros = new CobrosPorVendedorExport();
                $cobros->filter($request);

                return [$cobros, 'cobros-por-vendedor.xlsx'];
            }
        );
    }

    public function enviarReporteDiario()
    {
        try {
            $fecha = Carbon::today()->format('Y-m-d');
            $export = new VentasPorVendedorExport($fecha);
            $filename = "ventas-por-vendedor-{$fecha}.xlsx";

            $relativePath = "reportes/{$filename}";

            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            Storage::disk('public')->put($relativePath, '');


            Excel::store($export, $relativePath, 'public');


            $filePath = public_path('img/' . $relativePath);

            if (!file_exists($filePath)) {

                Log::error("Archivo no encontrado en: {$filePath}");

                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            $ventasDelDia = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->count();

            $totalVentas = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->where('estado', '!=', 'Anulada')
                ->sum('total');

            $vendedoresConVentas = Venta::where('fecha', $fecha)
                ->where('cotizacion', 0)
                ->distinct('id_vendedor')
                ->where('estado', '!=', 'Anulada')
                ->count('id_vendedor');

            $destinatarios = [
                'cristian.g@smartpyme.sv',
            ];

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath)
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            return response()->json(['message' => 'Reporte enviado correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte diario: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function enviarReporteProgramado($configuracion, $empresa, $fechaInicio, $fechaFin)
    {
        try {
            // $fecha = Carbon::today()->format('Y-m-d');
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $empresa->id);
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $empresa->id, $configuracion->sucursales);
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $empresa->id, $configuracion);
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $empresa->id,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
            } elseif ($configuracion->tipo_reporte === 'cobros-por-vendedor') {
                $request = new Request([
                    'id_empresa' => $empresa->id,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
            } elseif ($configuracion->tipo_reporte === 'ventas-compras-por-marca-proveedor') {
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $empresa->id,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-totales') {
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $empresa->id,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDesglosadasPorVendedorExport($requestVentasTotales);
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-por-producto') {
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $empresa->id,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
            }
            $filename = "{$configuracion->tipo_reporte}-{$fechaInicio}.xlsx";


            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($empresa->id);


            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');

            $filePath = public_path('img/' . $relativePath);

            if (!file_exists($filePath)) {
                Log::error("Archivo no encontrado en: {$filePath}");
                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('id_empresa', $empresa->id)
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            } else {
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-utilidades' => 'Reporte de Ventas por Utilidades ' . $fechaInicio . ' al ' . $fechaFin,
                'cobros-por-vendedor' => 'Reporte de Cobros por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-compras-por-marca-proveedor' => 'Reporte de Ventas y Compras por Marca y Proveedor ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-totales' => 'Reporte de Detalle de Ventas Totales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-por-producto' => 'Reporte de Detalle de Ventas por Producto ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;



            $datos = [
                'fecha' => $fechaInicio,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto,
                'automatico' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            $destinatarios = $configuracion->destinatarios;

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            // Registrar que se envió el reporte
            Log::info("Reporte enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);


            unlink($filePath);


            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte programado: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function enviarReporteProgramadoTest($configuracion, $destinatarios, $fechaInicio, $fechaFin)
    {
        try {
            if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                $filename = "ventas-por-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "ventas-por-categoria-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "estado-financiero-consolidado-sucursales-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-vendedor') {
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                $filename = "detalle-ventas-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'inventario-por-sucursal') {
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                $filename = "inventario-por-sucursal-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-por-utilidades') {
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                $filename = "ventas-por-utilidades-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'cobros-por-vendedor') {
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
                $filename = "cobros-por-vendedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'ventas-compras-por-marca-proveedor') {
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
                $filename = "ventas-compras-por-marca-proveedor-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-totales') {
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDesglosadasPorVendedorExport($requestVentasTotales);
                $filename = "detalle-ventas-totales-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            } elseif ($configuracion->tipo_reporte === 'detalle-ventas-por-producto') {
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
                $filename = "detalle-ventas-por-producto-prueba-{$fechaInicio}-{$fechaFin}-" . time() . ".xlsx";
            }

            $relativePath = "reportes/{$filename}";
            $empresa = Empresa::find($configuracion->id_empresa);

            $directory = public_path('img/reportes');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }


            Storage::disk('public')->put($relativePath, '');

            Excel::store($export, $relativePath, 'public');


            $filePath = public_path('img/' . $relativePath);


            if (!file_exists($filePath)) {

                Log::error("Archivo no encontrado en: {$filePath}");

                $alternativePath = storage_path('app/public/' . $relativePath);
                Log::info("Intentando ruta alternativa: {$alternativePath}");

                if (file_exists($alternativePath)) {
                    $filePath = $alternativePath;
                } else {
                    throw new \Exception("El archivo no fue generado correctamente. No se encuentra en ninguna de las rutas esperadas.");
                }
            }

            // Obtener estadísticas para incluir en el correo
            if($configuracion->tipo_reporte === 'ventas-por-vendedor') {
                $ventasDelDia = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->count();

                $totalVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->where('estado', '!=', 'Anulada')
                    ->sum('total');

                $vendedoresConVentas = Venta::whereBetween('fecha', [$fechaInicio, $fechaFin])
                    ->where('cotizacion', 0)
                    ->distinct('id_vendedor')
                    ->where('estado', '!=', 'Anulada')
                    ->count('id_vendedor');
            }else{
                $ventasDelDia = 0;
                $totalVentas = 0;
                $vendedoresConVentas = 0;
            }

            $asuntos_correos = [
                'ventas-por-vendedor' => 'Reporte de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-categoria-vendedor' => 'Reporte de Ventas por Categoría y Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'estado-financiero-consolidado-sucursales' => 'Reporte de Estado Financiero Consolidado por Sucursales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-vendedor' => 'Reporte de Detalle de Ventas por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'inventario-por-sucursal' => 'Reporte de Inventario por Sucursal ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-por-utilidades' => 'Reporte de Ventas por Utilidades ' . $fechaInicio . ' al ' . $fechaFin,
                'cobros-por-vendedor' => 'Reporte de Cobros por Vendedor ' . $fechaInicio . ' al ' . $fechaFin,
                'ventas-compras-por-marca-proveedor' => 'Reporte de Ventas y Compras por Marca y Proveedor ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-totales' => 'Reporte de Detalle de Ventas Totales ' . $fechaInicio . ' al ' . $fechaFin,
                'detalle-ventas-por-producto' => 'Reporte de Detalle de Ventas por Producto ' . $fechaInicio . ' al ' . $fechaFin,
            ];

            $asunto = $asuntos_correos[$configuracion->tipo_reporte] ?? $configuracion->asunto_correo;

            $datos = [
                'fecha' => Carbon::today()->format('d/m/Y'),
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'ventasDelDia' => $ventasDelDia,
                'totalVentas' => $totalVentas,
                'vendedoresConVentas' => $vendedoresConVentas,
                'archivoPath' => $filePath,
                'nombreArchivo' => basename($filePath),
                'asunto' => $asunto ?: "Reporte de Prueba: " . $configuracion->tipo_reporte . " - " . Carbon::today()->format('d/m/Y'),
                'esPrueba' => true,
                'tipo_reporte' => $configuracion->tipo_reporte,
                'empresa' => $empresa->nombre
            ];

            Mail::to($destinatarios)->send(new ReporteVentasPorVendedor($datos));

            Log::info("Reporte de prueba enviado: {$configuracion->tipo_reporte}", [
                'configuracion_id' => $configuracion->id,
                'destinatarios' => $destinatarios,
                'fecha' => $fechaInicio . ' al ' . $fechaFin
            ]);

            unlink($filePath);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte de prueba: ' . $e->getMessage(), [
                'configuracion_id' => $configuracion->id ?? null,
                'tipo_reporte' => $configuracion->tipo_reporte ?? null
            ]);
            throw $e;
        }
    }

    public function exportarReporteProgramado($configuracion, $fechaInicio, $fechaFin)
    {
        Log::info("Exportando reporte: {$configuracion->tipo_reporte}", [
            'configuracion_id' => $configuracion->id,
            'fecha' => $fechaInicio . ' al ' . $fechaFin,
        ]);

        // if ($configuracion->tipo_reporte === 'ventas-por-vendedor') {
        //     $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } elseif ($configuracion->tipo_reporte === 'ventas-por-categoria-vendedor') {
        //     $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
        // } elseif ($configuracion->tipo_reporte === 'estado-financiero-consolidado-sucursales') {
        //     $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
        // } else {
        //     return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        // }

        switch ($configuracion->tipo_reporte) {
            case 'ventas-por-vendedor':
                $export = new VentasPorVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'ventas-por-categoria-vendedor':
                $export = new VentasPorCategoriaVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'estado-financiero-consolidado-sucursales':
                $export = new EstadoFinancieroConsolidadoSucursalesExport($fechaInicio, $fechaFin, $configuracion->id_empresa);
                break;
            case 'detalle-ventas-vendedor':
                $export = new DetalleVentasVendedorExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion->sucursales);
                break;
            case 'inventario-por-sucursal':
                $export = new InventarioExport($fechaInicio, $fechaFin, $configuracion->id_empresa, $configuracion);
                break;
            case 'ventas-por-utilidades':
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasPorUtilidadesExport();
                $export->filter($request);
                break;
            case 'cobros-por-vendedor':
                $request = new Request([
                    'id_empresa' => $configuracion->id_empresa,
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_sucursal' => !empty($configuracion->sucursales) ? $configuracion->sucursales[0] : '',
                ]);
                $export = new CobrosPorVendedorExport();
                $export->filter($request);
                break;
            case 'ventas-compras-por-marca-proveedor':
                $export = new VentasComprasPorMarcaProveedorExport(
                    $fechaInicio,
                    $fechaFin,
                    $configuracion->id_empresa,
                    $configuracion,
                    $configuracion->sucursales ?? []
                );
                break;
            case 'detalle-ventas-totales':
                $requestVentasTotales = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDesglosadasPorVendedorExport($requestVentasTotales);
                break;
            case 'detalle-ventas-por-producto':
                $requestDetalleProducto = new Request([
                    'inicio' => $fechaInicio,
                    'fin' => $fechaFin,
                    'id_empresa' => $configuracion->id_empresa,
                    'sucursales' => $configuracion->sucursales ?? [],
                ]);
                $export = new VentasDetallesExport();
                $export->filter($requestDetalleProducto);
                break;
            default:
                return response()->json(['error' => 'Tipo de reporte no implementado'], 422);
        }

        return \Maatwebsite\Excel\Facades\Excel::download($export, $configuracion->tipo_reporte . '-' . $fechaInicio . '-' . $fechaFin . '.xlsx');
    }

    public function getNumerosIdentificacion()
    {
        $numsIds = Venta::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();

        return response()->json($numsIds, 200);
    }
}
