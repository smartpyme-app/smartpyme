<?php

namespace App\Http\Controllers\Api\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\Authorization\HasAutoAuthorization;

use App\Models\Admin\Documento;
use App\Models\Compras\Compra;
use App\Models\Compras\DevolucionCompra;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use Illuminate\Support\Facades\DB;
use App\Services\Bancos\TransaccionesService;
use App\Services\Bancos\ChequesService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Compras\ComprasAuthorizationService;
use App\Services\Compras\CompraService;
use App\Services\Compras\OrdenCompraService;

use App\Exports\ComprasExport;
use App\Exports\ComprasDetallesExport;
use App\Exports\RentabilidadSucursalExport;
use App\Models\OrdenCompra;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\Compras\StoreCompraRequest;
use App\Http\Requests\Compras\FacturacionCompraRequest;
use App\Http\Requests\Compras\FacturacionConsignaRequest;
use App\Http\Requests\Compras\GenerarCompraDesdeOrdenCompraRequest;

use Barryvdh\DomPDF\Facade as PDF;

class ComprasController extends Controller
{
    use HasAutoAuthorization;
    protected $authModule = 'compras';

    protected $transaccionesService;
    protected $chequesService;
    protected $authorizationService;
    protected $comprasAuthorizationService;
    protected $compraService;
    protected $ordenCompraService;

    public function __construct(
        TransaccionesService $transaccionesService,
        ChequesService $chequesService,
        AuthorizationService $authorizationService,
        ComprasAuthorizationService $comprasAuthorizationService,
        CompraService $compraService,
        OrdenCompraService $ordenCompraService
    ) {
        $this->transaccionesService = $transaccionesService;
        $this->chequesService = $chequesService;
        $this->authorizationService = $authorizationService;
        $this->comprasAuthorizationService = $comprasAuthorizationService;
        $this->compraService = $compraService;
        $this->ordenCompraService = $ordenCompraService;
    }

    public function index(Request $request) {

        $compras = Compra::with('retaceo')
            ->when($request->inicio && $request->fin, function ($query) use ($request) {
                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
            })
            ->when(!is_null($request->recurrente), function ($q) use ($request) {
                $valor = filter_var($request->recurrente, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if (!is_null($valor)) {
                    $q->where('recurrente', $valor);
                }
            })
            ->when($request->num_identificacion, fn($q) => $q->where('num_identificacion', $request->num_identificacion))
            ->when($request->id_sucursal, fn($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->when($request->id_bodega, fn($q) => $q->where('id_bodega', $request->id_bodega))
            ->when($request->id_usuario, fn($q) => $q->where('id_usuario', $request->id_usuario))
            ->when($request->id_proveedor, fn($q) => $q->where('id_proveedor', $request->id_proveedor))
            ->when($request->forma_pago, fn($q) => $q->where('forma_pago', $request->forma_pago))
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->metodo_pago, fn($q) => $q->where('metodo_pago', $request->metodo_pago))
            ->when($request->id_proyecto, fn($q) => $q->where('id_proyecto', $request->id_proyecto))
            ->when($request->dte !== null && $request->dte == 0, fn($q) => $q->whereNull('sello_mh'))
            ->when($request->dte !== null && $request->dte == 1, fn($q) => $q->whereNotNull('sello_mh'))
            ->when($request->es_retaceo !== null, function ($q) use ($request) {
                $valor = filter_var($request->es_retaceo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($valor === true) {
                    $q->where('es_retaceo', true)->whereHas('retaceo');
                } elseif ($valor === false) {
                    $q->where('es_retaceo', true)->whereDoesntHave('retaceo');
                }
            })
            ->where('cotizacion', 0)

            ->when($request->buscador, function ($query) use ($request) {
                $term = $request->buscador;
                $query->where(function ($q) use ($term) {
                    $q->whereHas('proveedor', function ($qp) use ($term) {
                            $qp->where('nombre', 'like', "%{$term}%")
                               ->orWhere('nombre_empresa', 'like', "%{$term}%")
                               ->orWhere('ncr', 'like', "%{$term}%")
                               ->orWhere('nit', 'like', "%{$term}%");
                        })
                      ->orWhere('referencia', 'like', "%{$term}%")
                      ->orWhere('estado', 'like', "%{$term}%")
                      ->orWhere('observaciones', 'like', "%{$term}%")
                      ->orWhere('forma_pago', 'like', "%{$term}%")
                      ->orWhere('num_identificacion', 'like', "%{$term}%")
                      ->orWhere('tipo_documento', 'like', "%{$term}%")
                      ->orWhereRaw("CONCAT(tipo_documento, ' #', referencia) LIKE ?", ["%{$term}%"])
                      ->orWhereHas('proyecto', function ($qp) use ($term) {
                          $qp->where('nombre', 'like', "%{$term}%");
                      });
                });
            })
            ->when($request->orden && $request->direccion, function ($q) use ($request) {
                $permitidas = ['id', 'fecha', 'referencia', 'estado', 'forma_pago'];
                $dir = strtolower($request->direccion) === 'asc' ? 'asc' : 'desc';
                $col = in_array($request->orden, $permitidas) ? $request->orden : 'id';
                $q->orderBy($col, $dir);
            })
            ->withSum(['abonos' => function ($query) {
                $query->where('estado', 'Confirmado');
            }], 'total')
            ->withSum(['devoluciones' => function ($query) {
                $query->where('enable', 1);
            }], 'total')
            ->withAccessorRelations()
            ->orderBy($request->orden, $request->direccion)
            ->orderBy('id', 'desc')
            ->paginate($request->paginate);

        foreach ($compras as $compra) {
            $compra->saldo = $compra->saldo;
        }

        return response()->json($compras, 200);
    }


    public function read($id)
    {
        $compra = Compra::where('id', $id)->with('detalles', 'proveedor', 'abonos', 'devoluciones')->firstOrFail();
        $compra->saldo = $compra->saldo;
        return Response()->json($compra, 200);
    }

    public function search($txt) {

        $compras = Compra::whereHas('proveedor', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })
                    ->paginate(10);

        return Response()->json($compras, 200);
    }

    public function filter(Request $request) {

        $compras = Compra::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->referencia, function($query) use ($request){
                                return $query->where('referencia', $request->referencia);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->id_proveedor, function($query) use ($request){
                                return $query->whereHas('proveedor', function($query) use ($request)
                                {
                                    $query->where('id_proveedor', $request->id_proveedor);

                                });
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($compras, 200);
    }


    public function store(StoreCompraRequest $request)
    {
        DB::beginTransaction();
        $data = $request->validated();

        if ($response = $this->checkAuth('store', $data)) {
            return $response;
        }

        $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();
        $orden = null;
        if ($compra->num_orden_compra) {
            $orden = OrdenCompra::where('id', $compra->num_orden_compra)->with("detalles")->first();
        }

        // Ajustar stocks
        foreach ($compra->detalles as $detalle) {

            $producto = Producto::where('id', $detalle->id_producto)
                ->with('composiciones')->firstOrFail();

            // Bloquear fila para evitar condiciones de carrera
            $inventario = Inventario::where('id_producto', $detalle->id_producto)
                ->where('id_bodega', $compra->id_bodega)
                ->lockForUpdate()
                ->first();

            // Anular compra y regresar stock
            if (($compra->estado != 'Anulada') && ($request['estado'] == 'Anulada')) {

                if ($inventario) {
                    // Actualizar stock de forma atómica
                    $inventario->stock -= $detalle->cantidad;
                    $inventario->save();
                    // Registrar kardex - si falla, la transacción hará rollback
                    $inventario->kardex($compra, $detalle->cantidad * -1);
                }
                //restaurar cantidad ingresada en orden de compra
                if ($orden) {
                    $detalle_orden = $orden->detalles->where('id_producto', $detalle->id_producto)->first();

                    $detalle_orden->cantidad_procesada = floatval($detalle_orden->cantidad_procesada) - floatval($detalle->cantidad);
                    // return [$det, $detalle_orden];
                    $detalle_orden->save();

                    $orden->estado = 'Pendiente';
                    $orden->save();
                }

                // Abonos
                foreach ($compra->abonos as $abono) {
                    $abono->estado = 'Cancelado';
                    $abono->save();
                }
            }
            // Cancelar anulación de compra y descargar stock
            if (($compra->estado == 'Anulada') && ($request['estado'] != 'Anulada')) {
                // Aplicar stock
                if ($inventario) {
                    // Actualizar stock de forma atómica
                    $inventario->stock += $detalle->cantidad;
                    $inventario->save();
                    // Registrar kardex - si falla, la transacción hará rollback
                    $inventario->kardex($compra, $detalle->cantidad);
                }

                // Abonos
                foreach ($compra->abonos as $abono) {
                    $abono->estado = 'Confirmado';
                    $abono->save();
                }
            }
        }

        $compra->fill($request->all());
        $compra->save();
        DB::commit();
        // return  $orden = OrdenCompra::where('id', $compra->num_orden_compra)->with("detalles")->first();

        return Response()->json($compra, 200);
    }

    public function delete($id)
    {
        $compra = Compra::where('id', $id)->with('detalles')->firstOrFail();
        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }

    public function facturacion(FacturacionCompraRequest $request)
    {

        Log::info("Facturacion - iniciando proceso");

        // VERIFICAR AUTORIZACIÓN usando ComprasAuthorizationService
        $authorizationResult = $this->comprasAuthorizationService->validarAutorizacionRequerida(
            $request,
            $request->id,
            $request->id_authorization
        );

        if ($authorizationResult['requires_authorization']) {
            return response()->json($authorizationResult, 403);
        }

        Log::info("Procesando compra normal o autorizada");

        DB::beginTransaction();

        try {
            // Crear o actualizar compra usando CompraService
            $compra = $this->compraService->crearOActualizarCompra($request->all());

            // Determinar si es una compra nueva
            $esNueva = !$request->id;
            $esCotizacion = $request->cotizacion == 1;

            // Procesar detalles con inventario usando CompraService
            $this->compraService->procesarDetallesConInventario(
                $compra,
                $request->detalles,
                $esNueva,
                $esCotizacion
            );

            // Actualizar orden de compra si aplica usando OrdenCompraService
            if ($compra->num_orden_compra) {
                $this->ordenCompraService->actualizarDesdeCompra($compra, $request->detalles);
            }

            // Procesar pagos usando CompraService
            $this->compraService->procesarPagos($compra, $esNueva, $esCotizacion);

            // Incrementar correlativo usando CompraService
            if ($esNueva && $request->tipo_documento) {
                $this->compraService->incrementarCorrelativo($compra, $request->tipo_documento);
            }

            DB::commit();
            return Response()->json($compra, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function facturacionConsigna(FacturacionConsignaRequest $request)
    {

        DB::beginTransaction();

        try {
            $compra = Compra::where('id', $request->id)->with('detalles')->firstOrFail();
            if ($compra->total != $request->total) {
                // Crear consigna
                $consigna = new Compra();
                $consigna->fill($request->all());
                $consigna->estado = 'Consigna';
                $consigna->sub_total = $compra->sub_total - $request->sub_total;
                $consigna->total = $compra->total - $request->total;
                $consigna->iva = $compra->iva - $request->iva;
                $consigna->save();

                foreach ($request->detalles as $detalle) {

                    $detalle_compra = $compra->detalles()->where('id', $detalle['id'])->first();
                    if ($detalle_compra) {
                        if ($detalle_compra->cantidad > $detalle['cantidad']) {
                            $detalle_consigna = new Detalle();
                            $detalle_consigna->id_producto = $detalle['id_producto'];
                            $detalle_consigna->costo = $detalle['costo'];
                            $detalle_consigna->cantidad = $detalle_compra->cantidad - $detalle['cantidad'];
                            $detalle_consigna->total = $detalle_consigna->costo * $detalle_consigna->cantidad;
                            $detalle_consigna->id_compra = $consigna->id;
                            $detalle_consigna->save();
                        }
                    }
                }

                //Guardar nuevos detalles
                $compra->detalles()->delete();

                foreach ($request->detalles as $detalle) {
                    if ($detalle['cantidad'] > 0) {
                        $det = new Detalle();
                        $det->id_producto = $detalle['id_producto'];
                        $det->cantidad = $detalle['cantidad'];
                        $det->costo = $detalle['costo'];
                        $det->total = $detalle['cantidad'] * $detalle['costo'];
                        $det->descuento = 0;
                        $det->id_compra = $compra->id;
                        $det->save();
                    }
                }

                $compra->total = $request->total;
                $compra->iva = $request->iva;
                $compra->sub_total = $request->sub_total;
            }


            $compra->fecha = $request->fecha;
            $compra->estado = 'Pagada';
            $compra->save();

            DB::commit();
            return Response()->json($compra, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function libroCompras(Request $request) {
        $star = $request->inicio;
        $end = $request->fin;

        $compras = Compra::with('proveedor')->where('estado', '!=', 'Anulada')
                            ->when($request->tipo_documento, function($query) use ($request){
                                return $query->whereHas('documento', function($q) use ($request) {
                                        $q->where('nombre', $request->tipo_documento);
                                    });
                            })
                            ->when($request->id_sucursal, function($q) use ($request){
                                $q->where('id_sucursal', $request->id_sucursal);
                            })
                            ->whereBetween('fecha', [$request->inicio, $request->fin])
                            ->where('cotizacion', 0)
                            ->orderBy('id', 'desc')->get();

        $ivas = collect();

        foreach ($compras as $compra) {
                $ivas->push([
                    'fecha'                 => $compra->fecha,
                    'clase_documento'       => 1,
                    'tipo_documento'        => $compra->tipo_documento,
                    'num_documento'         => $compra->referencia,
                    'nit_nrc'               => $compra->proveedor()->pluck('nit')->first() ? $compra->proveedor()->pluck('nit')->first() : $compra->proveedor()->pluck('ncr')->first(),
                    'nombre_proveedor'        => $compra->nombre_proveedor,
                    'compras_exentas'        => $compra->exenta,
                    'compras_no_sujetas'     => $compra->no_sujeta,
                    'compras_gravadas'       => $compra->sub_total,
                    'debito_fiscal'         => $compra->iva,
                    'compras_cuenta_terceros'=> 0,
                    'debito_cuenta_terceros'=> 0,
                    'total'                 => $compra->total,
                    'dui'                   => $compra->proveedor()->pluck('dui')->first(),
                    'num_anexto'            => 1,
                ]);
        }

        // $ivas = $ivas->sortByDesc('correlativo')->values()->all();

        return Response()->json($ivas, 200);

    }


    public function detalles($id)
    {
        $compra = Compra::findOrFail($id);

        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }


    public function comprasProveedor($id)
    {

        $compras = Compra::where('id_proveedor', $id)->orderBy('estado', 'asc')->paginate(10);

        return Response()->json($compras, 200);
    }

    public function cxp()
    {

        $pagos = Compra::where('estado', 'Pendiente')->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($pagos, 200);
    }

    public function cxpBuscar($txt)
    {

        $pagos = Compra::where('estado', 'Pendiente')
            ->whereHas('proveedor', function ($query) use ($txt) {
                $query->where('nombre', 'like', '%' . $txt . '%');
            })
            ->orderBy('fecha', 'desc')->paginate(10);

        return Response()->json($pagos, 200);
    }

    public function historial(Request $request)
    {

        $compras = Compra::where('estado', 'Pagada')->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->fecha)->format('d-m-Y');
            });

        $movimientos = collect();

        foreach ($compras as $compra) {
            $movimientos->push([
                'cantidad'      => $compra->count(),
                'fecha'         => $compra[0]->fecha,
                'subtotal'      => $compra->sum('subtotal'),
                'iva'           => $compra->sum('iva'),
                'total'         => $compra->sum('total'),
                'detalles'      => $compra
            ]);
        }

        return Response()->json($movimientos, 200);
    }

    public function export(Request $request)
    {
        $compras = new ComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras.xlsx');
    }

    public function exportDetalles(Request $request)
    {
        $compras = new ComprasDetallesExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras-detalles.xlsx');
    }

    public function sinDevolucion()
    {

        $compras = Compra::where('estado', '!=', 'Anulada')
            ->whereMonth('fecha', '>=', date('m') - 1)
            ->whereYear('fecha', date('Y'))
            ->whereDoesntHave('devoluciones')
            ->orderBy('fecha', 'DESC')
            ->get();

        return Response()->json($compras, 200);
    }

    public function exportRentabilidad(Request $request)
    {

        //enviar id de la empresa en el request

        $user = JWTAuth::parseToken()->authenticate();
        $request->request->add(['id_empresa' => $user->id_empresa]);
        $ventas = new RentabilidadSucursalExport();
        $ventas->filter($request);

        return Excel::download($ventas, 'corte.xlsx');
    }


    public function getNumerosIdentificacion(){
        $numsIds = Compra::select('num_identificacion')
            ->distinct()
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereNotNull('num_identificacion')
            ->where('num_identificacion', '!=', '')
            ->get();

        return Response()->json($numsIds, 200);
    }

    public function procesarCompraAutorizada($compraId)
    {
        Log::info("Procesando compra autorizada: " . $compraId);

        DB::beginTransaction();

        try {
            $compra = Compra::findOrFail($compraId);

            // Cambiar estado a procesada
            $compra->estado = 'Pagada';
            $compra->save();

            // Actualizar inventarios (que no se hizo cuando estaba pendiente)
            foreach ($compra->detalles as $detalle) {
                if ($compra->cotizacion == 0) {
                    // Bloquear fila para evitar condiciones de carrera
                    $inventario = Inventario::where('id_producto', $detalle->id_producto)
                                           ->where('id_bodega', $compra->id_bodega)
                                           ->lockForUpdate()
                                           ->first();

                    if ($inventario) {
                        // Actualizar stock de forma atómica
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        // Registrar kardex - si falla, la transacción hará rollback
                        $inventario->kardex($compra, $detalle->cantidad, null, $detalle->costo);
                    }

                    // Actualizar costo del producto
                    $producto = $detalle->producto()->with('inventarios')->first();
                    if ($producto) {
                        $stock_anterior = ($producto->inventarios->sum('stock') ?? 0) - $detalle->cantidad;
                        $stock_actual = $detalle->cantidad;
                        $stock_total = $stock_anterior + $stock_actual;

                        if ($stock_total > 0) {
                            $costo_promedio = (($stock_anterior * $producto->costo) + ($stock_actual * $detalle->costo)) / $stock_total;
                        } else {
                            $costo_promedio = $detalle->costo;
                        }

                        $producto->costo_anterior = $producto->costo;
                        $producto->costo = $detalle->costo;
                        $producto->costo_promedio = $costo_promedio;
                        $producto->save();
                    }
                }
            }

            // Crear transacciones bancarias si aplica
            if ($compra->cotizacion == 0 && $compra->forma_pago != 'Efectivo' && $compra->forma_pago != 'Cheque') {
                $this->transaccionesService->crear($compra, 'Cargo', 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }

            if ($compra->cotizacion == 0 && $compra->forma_pago == 'Cheque') {
                $this->chequesService->crear($compra, $compra->nombre_proveedor, 'Compra: ' . $compra->tipo_documento . ' #' . ($compra->referencia ? $compra->referencia : ''), 'Compra');
            }

            DB::commit();

            Log::info("Compra autorizada procesada exitosamente: " . $compraId);

            return $compra;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error procesando compra autorizada: " . $e->getMessage());
            throw $e;
        }
    }

    public function generarCompraDesdeOrdenCompra(GenerarCompraDesdeOrdenCompraRequest $request){

        DB::beginTransaction();

        try {
            // Buscar la venta
            $venta = \App\Models\Ventas\Venta::where('id', $request->id)
                ->with('detalles', function($query) use ($request){
                    $query->withoutGlobalScope('empresa');
                }, 'cliente')
                ->firstOrFail();

            $orden_compra = Compra::withoutGlobalScope('empresa')->where('id', $request->num_orden)
                ->where('cotizacion', 1)
                ->with('detalles', 'proveedor')
                ->firstOrFail();

            // Buscar si ya existe una compra con el mismo tipo de documento, proveedor y correlativo
            $compraExistente = Compra::withoutGlobalScope('empresa')->where('tipo_documento', $venta->nombre_documento)
                ->where('id_proveedor', $orden_compra->id_proveedor)
                ->where('referencia', $venta->correlativo)
                ->where('id_empresa', $orden_compra->id_empresa)
                ->where('cotizacion', 0)
                ->first();

            if ($compraExistente) {
                return Response()->json([
                    'error' => 'Ya existe una compra con este tipo de documento, proveedor y correlativo.',
                ], 403);
            }

            // Crear la nueva compra basada en la venta
            $compra = new Compra();

            // Configurar campos básicos de la compra
            $compra->fecha = $venta->fecha;
            $compra->estado = $venta->estado;
            $compra->tipo_documento = $venta->nombre_documento;
            $compra->referencia = $venta->correlativo;
            $compra->forma_pago = $venta->forma_pago;
            $compra->fecha_pago = $venta->fecha_pago;
            $compra->id_usuario = $orden_compra->id_usuario;
            $compra->id_empresa = $orden_compra->id_empresa;
            $compra->id_bodega = $orden_compra->id_bodega;
            $compra->id_sucursal = $orden_compra->id_sucursal;
            $compra->cotizacion = 0; // Marcar como compra real, no cotización
            $compra->id_proveedor = $orden_compra->id_proveedor; // Usar el cliente como proveedor

            $compra->sub_total = $venta->sub_total;
            $compra->iva = $venta->iva;
            $compra->total = $venta->total;

            $compra->save();

            // Crear detalles de la compra a partir de los detalles de la venta
            foreach ($venta->detalles as $detalle_venta) {
                // Obtener el código del producto de la venta
                $producto_venta = \App\Models\Inventario\Producto::withoutGlobalScope('empresa')->where('id_empresa', $orden_compra->id_empresa)->where('codigo', $detalle_venta->codigo)->firstOrFail();
                if ($producto_venta) {
                    $detalle_compra = new Detalle();
                    $detalle_compra->id_producto = $producto_venta->id; // ID del producto en la empresa hija
                    $detalle_compra->cantidad = $detalle_venta->cantidad;
                    $detalle_compra->costo = $detalle_venta->precio; // Precio de la venta como costo
                    $detalle_compra->total = $detalle_venta->total;
                    $detalle_compra->descuento = $detalle_venta->descuento ?? 0;
                    $detalle_compra->id_compra = $compra->id;
                    $detalle_compra->save();

                    // Actualizar costo producto al de la ultima compra
                    $producto_venta->costo_anterior   = $producto_venta->costo;
                    $producto_venta->costo            = $detalle_venta->precio;
                    $producto_venta->costo_promedio   = $detalle_venta->precio;
                    $producto_venta->save();

                    // Actualizar inventario
                    // Bloquear fila para evitar condiciones de carrera
                    $inventario = Inventario::withoutGlobalScope('empresa')->where('id_producto', $producto_venta->id)
                        ->where('id_bodega', $compra->id_bodega)
                        ->lockForUpdate()
                        ->first();

                    if ($inventario) {
                        // Actualizar stock de forma atómica
                        $inventario->stock += $detalle_venta->cantidad;
                        $inventario->save();
                        // Registrar kardex - si falla, la transacción hará rollback
                        $inventario->kardex($compra, $detalle_venta->cantidad);
                    }

                }
            }

            $orden_compra->estado = 'Aceptada';
            $orden_compra->save();

            DB::commit();
            return Response()->json($compra, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    protected function handlePendingAuthorization($data, $authorization)
    {
        Log::info("Creando compra pendiente de autorización");

        DB::beginTransaction();

        try {
            // Crear compra en estado pendiente
            $compraData = $data;
            $compraData['estado'] = 'Pendiente Autorización';
            $compraData['id_authorization'] = $authorization->id;
            $compraData['id_sucursal'] = Auth::user()->id_sucursal;

            $compra = new Compra;
            $compra->fill($compraData);
            $compra->save();

            // Crear detalles de la compra pendiente (sin actualizar inventario)
            foreach ($data['detalles'] as $det) {
                $detalle = new Detalle;
                $det['id_compra'] = $compra->id;
                $detalle->fill($det);
                $detalle->save();
            }

            // Actualizar la autorización con el ID de la compra creada
            $authorization->update([
                'authorizeable_id' => $compra->id
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'data' => $compra,
                'estado' => 'Pendiente Autorización',
                'requires_authorization' => true,
                'authorization_code' => $authorization->code,
                'message' => 'Compra creada pendiente de autorización'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error creando compra pendiente: " . $e->getMessage());

            return response()->json([
                'ok' => false,
                'requires_authorization' => true,
                'authorization_type' => $authorization->authorizationType->name,
                'message' => 'Error al crear compra pendiente: ' . $e->getMessage(),
                'authorization_code' => $authorization->code
            ], 403);
        }
    }

    public function generarDoc($id){
        $compra = Compra::where('id', $id)->with('detalles', 'proveedor', 'empresa')->firstOrFail();

        $pdf = PDF::loadView('reportes.facturacion.compra', compact('compra'));
        $pdf->setPaper('US Letter', 'portrait');
        return $pdf->stream('compra-' . $compra->id . '.pdf');

    }

    public function marcarRecurrente(Request $request)
    {
        $compra = Compra::findOrFail($request->id);
        $compra->recurrente = false;
        $compra->save();

        return response()->json([
            'message' => 'Compra marcada como no recurrente',
            'compra'  => $compra
        ], 200);
    }
}
