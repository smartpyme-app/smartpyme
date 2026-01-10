<?php

namespace App\Http\Controllers\Api\Contabilidad\Partidas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\SaldoMensual;
use App\Models\User;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Abono as AbonoVenta;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Abono as AbonoCompra;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\FormaDePago;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use App\Services\Contabilidad\CierreMesService;
use App\Services\Contabilidad\SimulacionCierreService;
use App\Services\Contabilidad\Partidas\PartidaService;
use App\Services\Contabilidad\Partidas\PartidaIngresosService;
use App\Services\Contabilidad\Partidas\PartidaEgresosService;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Http\Requests\Contabilidad\Partidas\StorePartidaRequest;
use App\Http\Requests\Contabilidad\Partidas\ReordenarCorrelativosRequest;
use App\Http\Requests\Contabilidad\Partidas\GenerarIngresosRequest;
use App\Http\Requests\Contabilidad\Partidas\GenerarCxCRequest;
use App\Http\Requests\Contabilidad\Partidas\GenerarEgresosRequest;
use App\Http\Requests\Contabilidad\Partidas\GenerarCxPRequest;
use App\Http\Requests\Contabilidad\Partidas\CerrarPartidasRequest;
use App\Http\Requests\Contabilidad\Partidas\AbrirPartidaRequest;
use App\Http\Requests\Contabilidad\Partidas\ReabrirPeriodoRequest;
use App\Http\Requests\Contabilidad\Partidas\VerificarEstadoPeriodoRequest;
use App\Http\Requests\Contabilidad\Partidas\ObtenerBalanceComprobacionRequest;
use App\Http\Requests\Contabilidad\Partidas\SimularCierreMesRequest;

class PartidasController extends Controller
{
    protected $partidaService;
    protected $partidaIngresosService;
    protected $partidaEgresosService;

    public function __construct(
        PartidaService $partidaService,
        PartidaIngresosService $partidaIngresosService,
        PartidaEgresosService $partidaEgresosService
    ) {
        $this->partidaService = $partidaService;
        $this->partidaIngresosService = $partidaIngresosService;
        $this->partidaEgresosService = $partidaEgresosService;
    }

    // public function index(Request $request) {

    //     $partidas = Partida::with('detalles')->when($request->buscador, function($query) use ($request){
    //                                 return $query->where('concepto', 'like' ,'%' . $request->buscador . '%')
    //                                             ->orwhere('tipo', 'like' ,'%' . $request->buscador . '%');
    //                             })
    //                             ->when($request->inicio, function($query) use ($request){
    //                                 return $query->where('fecha', '>=', $request->inicio);
    //                             })
    //                             ->when($request->fin, function($query) use ($request){
    //                                 return $query->where('fecha', '<=', $request->fin);
    //                             })
    //                             ->when($request->estado, function($query) use ($request){
    //                                 return $query->where('estado', $request->estado);
    //                             })
    //                             ->when($request->tipo, function($query) use ($request){
    //                                 return $query->where('tipo', $request->tipo);
    //                             })
    //                             ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
    //                             ->paginate($request->paginate);

    //     $partidas = $partidas->toArray();
    //     $partidas['total_pendientes'] = Partida::where('estado', 'Pendiente')->count();

    //     return Response()->json($partidas, 200);

    // }

    public function index(Request $request) {
        $query = Partida::select([
                'partidas.*',
                DB::raw('COALESCE(SUM(partida_detalles.debe), 0) as total_debe'),
                DB::raw('COALESCE(SUM(partida_detalles.haber), 0) as total_haber')
            ])
            ->leftJoin('partida_detalles', 'partidas.id', '=', 'partida_detalles.id_partida')
            ->groupBy('partidas.id', 'partidas.fecha', 'partidas.tipo', 'partidas.correlativo',
                     'partidas.concepto', 'partidas.estado', 'partidas.referencia',
                     'partidas.id_referencia', 'partidas.id_usuario', 'partidas.id_empresa',
                     'partidas.created_at', 'partidas.updated_at');

        if ($request->has('incluir_anuladas') &&
            ($request->incluir_anuladas === true ||
            $request->incluir_anuladas === 'true' ||
            $request->incluir_anuladas === '1' ||
            $request->incluir_anuladas === 1)) {

            //mostrara solo anuladas
            $query->where('partidas.estado', 'Anulada');
        } else {
            // mostrara todas excepto anuladas
            $query->where('partidas.estado', '!=', 'Anulada');
        }


        // Filtros existentes
        $query->when($request->buscador, function($q) use ($request){
            return $q->where(function($subQ) use ($request) {
                $subQ->where('partidas.concepto', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.tipo', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.correlativo', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.id', 'like' ,'%' . $request->buscador . '%');
            });
        })
        ->when($request->inicio, function($q) use ($request){
            return $q->where('partidas.fecha', '>=', $request->inicio);
        })
        ->when($request->fin, function($q) use ($request){
            return $q->where('partidas.fecha', '<=', $request->fin);
        })
        ->when($request->estado, function($q) use ($request){
            return $q->where('partidas.estado', $request->estado);
        })
        ->when($request->tipo, function($q) use ($request){
            return $q->where('partidas.tipo', $request->tipo);
        });

        // Ordenamiento por correlativo por defecto
        $orden = $request->orden ?: 'correlativo';
        $direccion = $request->direccion ?: 'desc';

        $partidas = $query->orderBy($orden, $direccion)->paginate($request->paginate ?: 10);

        // Calcular totales generales
        $totalesGenerales = $this->partidaService->calcularTotalesGenerales($request, auth()->user()->id_empresa);

        $response = $partidas->toArray();
        $response['total_pendientes'] = Partida::where('estado', 'Pendiente')->count();
        $response['totales_generales'] = $totalesGenerales;

        return Response()->json($response, 200);
    }

    public function list() {
        $partidas = Partida::orderby('correlativo', 'desc')
                                ->where('estado', '!=', 'Anulada')
                                ->get();

        return Response()->json($partidas, 200);
    }

    // public function list() {

    //     $partidas = Partida::orderby('nombre')
    //                             // ->where('activo', true)
    //                             ->get();

    //     return Response()->json($partidas, 200);

    // }

    public function read($id) {
        // Optimizar carga de detalles para evitar problemas con partidas grandes
        $partida = Partida::where('id', $id)->firstOrFail();

        // Contar total de detalles
        $totalDetalles = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)->count();

        // Calcular totales desde la base de datos (más eficiente)
        $totales = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)
            ->selectRaw('COALESCE(SUM(debe), 0) as total_debe, COALESCE(SUM(haber), 0) as total_haber')
            ->first();

        // Cargar solo los primeros 100 detalles para evitar problemas de memoria
        $perPage = 100;
        $detalles = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)
            ->select(['id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta',
                     'concepto', 'debe', 'haber', 'saldo', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->limit($perPage)
            ->get();

        // Asignar detalles a la partida
        $partida->setRelation('detalles', $detalles);

        // Agregar información de paginación y totales
        $partida->total_detalles = $totalDetalles;
        $partida->detalles_cargados = $detalles->count();
        $partida->tiene_mas_detalles = $totalDetalles > $perPage;
        $partida->per_page = $perPage;
        $partida->total_debe = $totales->total_debe ?? 0;
        $partida->total_haber = $totales->total_haber ?? 0;

        \Log::info('Partida cargada', [
            'partida_id' => $id,
            'total_detalles' => $totalDetalles,
            'detalles_cargados' => $detalles->count(),
            'tiene_mas_detalles' => $partida->tiene_mas_detalles,
            'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);

        return Response()->json($partida, 200);
    }

    /**
     * Obtener detalles paginados de una partida
     */
    public function getDetalles(Request $request, $id) {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 100);
        $offset = ($page - 1) * $perPage;

        // Verificar que la partida existe
        $partida = Partida::where('id', $id)->firstOrFail();

        // Contar total de detalles
        $totalDetalles = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)->count();

        // Calcular última página correctamente
        $lastPage = (int)ceil($totalDetalles / $perPage);

        // Obtener detalles paginados
        $detalles = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)
            ->select(['id', 'id_partida', 'id_cuenta', 'codigo', 'nombre_cuenta',
                     'concepto', 'debe', 'haber', 'saldo', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Calcular si hay más páginas
        $hasMore = $page < $lastPage;

        \Log::info('Detalles paginados solicitados', [
            'partida_id' => $id,
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
            'total_detalles' => $totalDetalles,
            'detalles_retornados' => $detalles->count(),
            'last_page' => $lastPage,
            'has_more' => $hasMore
        ]);

        return Response()->json([
            'detalles' => $detalles,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalDetalles,
                'last_page' => $lastPage,
                'has_more' => $hasMore
            ]
        ], 200);
    }

    /**
     * Recalcular totales considerando detalles modificados
     * Recibe los detalles modificados y recalcula los totales sumando:
     * - Los detalles modificados (con sus nuevos valores)
     * - Los detalles no modificados (desde la BD)
     */
    public function recalcularTotales(Request $request, $id) {
        $request->validate([
            'detalles_modificados' => 'required|array',
            'detalles_modificados.*.id' => 'sometimes|integer',
            'detalles_modificados.*.debe' => 'sometimes|nullable|numeric',
            'detalles_modificados.*.haber' => 'sometimes|nullable|numeric',
        ]);

        // Obtener todos los detalles de la partida desde la BD
        $todosDetallesBD = \App\Models\Contabilidad\Partidas\Detalle::where('id_partida', $id)
            ->select(['id', 'debe', 'haber'])
            ->get()
            ->keyBy('id');

        // Crear un mapa de los detalles modificados
        $detallesModificadosMap = [];
        foreach ($request->detalles_modificados as $detalleModificado) {
            if (isset($detalleModificado['id'])) {
                $detallesModificadosMap[$detalleModificado['id']] = [
                    'debe' => $detalleModificado['debe'] ?? null,
                    'haber' => $detalleModificado['haber'] ?? null
                ];
            }
        }

        // Calcular totales: usar valores modificados si existen, sino usar valores de BD
        $totalDebe = 0;
        $totalHaber = 0;

        foreach ($todosDetallesBD as $idDetalle => $detalleBD) {
            if (isset($detallesModificadosMap[$idDetalle])) {
                // Usar valor modificado
                $debe = $detallesModificadosMap[$idDetalle]['debe'];
                $haber = $detallesModificadosMap[$idDetalle]['haber'];
            } else {
                // Usar valor de BD
                $debe = $detalleBD->debe;
                $haber = $detalleBD->haber;
            }

            // Normalizar valores null a 0
            $debe = $debe === null || $debe === '' ? 0 : (float)$debe;
            $haber = $haber === null || $haber === '' ? 0 : (float)$haber;

            $totalDebe += $debe;
            $totalHaber += $haber;
        }

        \Log::info('Totales recalculados', [
            'partida_id' => $id,
            'detalles_modificados' => count($detallesModificadosMap),
            'total_detalles' => $todosDetallesBD->count(),
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'diferencia' => $totalDebe - $totalHaber
        ]);

        return Response()->json([
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'diferencia' => round($totalDebe - $totalHaber, 2)
        ], 200);
    }

    public function store(StorePartidaRequest $request)
    {
        try {
            $partida = $this->partidaService->crearOActualizar($request->all());
            return Response()->json($partida, 200);
        } catch (\Exception $e) {
            \Log::error('Error en store partida', [
                'partida_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            \Log::error('Error en store partida (Throwable)', [
                'partida_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
    * Método para reordenar correlativos
    */
    public function reordenarCorrelativos(ReordenarCorrelativosRequest $request)
    {
        // Si viene el parámetro 'todos', reordenar toda la empresa
        if ($request->has('todos') && $request->todos) {
            try {
                // Usar Opción 2: reutilizar método existente
                $tipos = ['Ingreso', 'Egreso', 'Diario', 'CxC', 'CxP', 'Cierre'];
                $totalReordenadas = 0;

                foreach ($tipos as $tipo) {
                    // Obtener todos los meses/años que tienen partidas de este tipo
                    $mesesConPartidas = DB::table('partidas')
                        ->where('id_empresa', auth()->user()->id_empresa)
                        ->where('tipo', $tipo)
                        ->where('estado', '!=', 'Anulada')
                        ->selectRaw('YEAR(fecha) as anio, MONTH(fecha) as mes')
                        ->groupBy('anio', 'mes')
                        ->orderBy('anio')
                        ->orderBy('mes')
                        ->get();

                    foreach ($mesesConPartidas as $periodo) {
                        $reordenadas = Partida::reordenarCorrelativos(
                            $periodo->anio,
                            str_pad($periodo->mes, 2, '0', STR_PAD_LEFT),
                            $tipo,
                            auth()->user()->id_empresa
                        );
                        $totalReordenadas += $reordenadas;
                    }
                }

                return response()->json([
                    'message' => 'Todos los correlativos han sido reordenados exitosamente',
                    'partidas_reordenadas' => $totalReordenadas
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Error al reordenar todos los correlativos: ' . $e->getMessage()
                ], 500);
            }
        }

        try {
            $partidasReordenadas = Partida::reordenarCorrelativos(
                $request->anio,
                str_pad($request->mes, 2, '0', STR_PAD_LEFT),
                $request->tipo,
                auth()->user()->id_empresa
            );

            return response()->json([
                'message' => 'Correlativos reordenados exitosamente',
                'partidas_reordenadas' => $partidasReordenadas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al reordenar correlativos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generarIngresos(GenerarIngresosRequest $request)
    {
        try {
            $resultado = $this->partidaIngresosService->generarPartidaIngresos(
                $request->fecha,
                auth()->user()->id,
                auth()->user()->id_empresa
            );

            // Si no hay ingresos, retornar respuesta vacía
            if (isset($resultado['partida']) && empty($resultado['detalles'])) {
                return Response()->json($resultado, 200);
            }

            // Si se guardó exitosamente, retornar el resultado
            return response()->json($resultado, 200);

            } catch (\Exception $e) {
            \Log::error('Error en generarIngresos', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $request->fecha
            ]);

            // Manejar errores específicos
            if (strpos($e->getMessage(), 'no tiene cuenta contable') !== false) {
                return Response()->json([
                    'titulo' => $e->getMessage(),
                    'error' => $e->getMessage(),
                    'code' => 400
                ], 400);
            }

            return Response()->json([
                'error' => 'Error al generar ingresos: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function generarCxC(GenerarCxCRequest $request)
    {

        $configuracion = Configuracion::first();
        $ventas = Venta::where('estado', 'Pendiente')
                        ->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'CxC',
                'concepto' => 'Registro de cxc',
                'estado' => 'Pendiente',
            ];

        // Detalles

            $detalles = [];
            $cuenta_cxc = Cuenta::where('id', $configuracion->id_cuenta_cxc)->first();
            if(!$cuenta_cxc){
                return  Response()->json(['titulo' => 'No hay cuenta contable.', 'error' => 'No esta configurada la cuenta contable para cuentas por cobrar.', 'code' => 400], 400);
            }
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_ventas)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_ventas)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_venta)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();

            foreach ($ventas as $venta) {

                $detalles[] = [
                    'id_cuenta' => $cuenta_cxc->id,
                    'codigo' => $cuenta_cxc->codigo,
                    'nombre_cuenta' => $cuenta_cxc->nombre,
                    'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                    'debe' => $venta->total,
                    'haber' => NULL,
                    'saldo' => 0,
                ];

                $productos_venta = DetalleVenta::with('producto')->where('id_venta', $venta->id)->get();

                foreach ($productos_venta as $detalle) {
                    $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                    if($id_categoria){
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->first();

                        if(!$cuenta_categoria_sucursal){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();

                        if(!$cuenta){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => $detalle->total,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_cxc->id,
                            'codigo' => $cuenta_cxc->codigo,
                            'nombre_cuenta' => $cuenta_cxc->nombre,
                            'concepto' => 'Inventarios ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => $venta->sub_total,
                            'saldo' => 0,
                            'productos' => $productos_venta,
                        ];
                        break;
                    }
                }

                if ($venta->iva > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva->id,
                        'codigo' => $cuenta_iva->codigo,
                        'nombre_cuenta' => $cuenta_iva->nombre,
                        'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                        'debe' => NULL,
                        'haber' => $venta->iva,
                        'saldo' => 0,
                    ];
                }

                if ($venta->iva_retenido > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Ingresos por cxc ' . $venta->nombre_documento . '#' . $venta->correlativo,
                        'debe' => $venta->iva_retenido,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }


                // Costo de venta

                    $productos_venta = DetalleVenta::with('producto')->where('id_venta', $venta->id)->get();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $venta->id_sucursal)->first();

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $cuenta_costos = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_costo)->first();

                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => $this->partidaService->normalizarDecimal($detalle->costo * $detalle->cantidad),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];

                            $cuenta_inventarios = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios  ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => NULL,
                                'haber' => $this->partidaService->normalizarDecimal($detalle->costo * $detalle->cantidad),
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => $venta->total_costo,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $venta->nombre_documento . '#' . $venta->correlativo,
                                'debe' => NULL,
                                'haber' => $venta->total_costo,
                                'saldo' => 0,
                            ];
                        }
                    }


            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarEgresos(GenerarEgresosRequest $request)
    {
        try {
            $resultado = $this->partidaEgresosService->generarPartidaEgresos($request->fecha);
            return Response()->json($resultado, 200);
        } catch (\Exception $e) {
            \Log::error('Error en generarEgresos', [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $request->fecha
            ]);

            // Manejar errores específicos
            if (strpos($e->getMessage(), 'no tiene cuenta contable') !== false) {
                return Response()->json([
                    'titulo' => $e->getMessage(),
                    'error' => $e->getMessage(),
                    'code' => 400
                ], 400);
            }

            return Response()->json([
                'error' => 'Error al generar egresos: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function generarCxP(GenerarCxPRequest $request)
    {

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pendiente')->where('fecha', $request->fecha)->get();

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'CxP',
                'concepto' => 'Compra de mercancía al crédito',
                'estado' => 'Pendiente',
            ];

        // Detalles


            $detalles = [];
            $cuenta_cxp = Cuenta::where('id', $configuracion->id_cuenta_cxp)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_compra)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();
            $cuenta_renta_retenida = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();

            foreach ($compras as $compra) {

                $detalles[] = [
                    'id_cuenta'         => $cuenta_cxp->id,
                    'codigo'            => $cuenta_cxp->codigo,
                    'nombre_cuenta'     => $cuenta_cxp->nombre,
                    'concepto'          => 'Compra de mercancía al crédito',
                    'debe'              => NULL,
                    'haber'             => $compra->total,
                    'saldo'             => 0
                ];

                $productos_compra = DetalleCompra::with('producto')->where('id_compra', $compra->id)->get();

                foreach ($productos_compra as $detalle) {
                    $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                    if($id_categoria){
                        $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $compra->id_sucursal)->first();

                        if(!$cuenta_categoria_sucursal){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->correlativo, 'code' => 400], 400);
                        }

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_ingresos)->first();

                        if(!$cuenta){
                            return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $compra->correlativo, 'code' => 400], 400);
                        }

                        $detalles[] = [
                            'id_cuenta' => $cuenta->id,
                            'codigo' => $cuenta->codigo,
                            'nombre_cuenta' => $cuenta->nombre,
                            'concepto' => 'Compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                            'debe' => $detalle->total,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }else{
                        $detalles[] = [
                            'id_cuenta' => $cuenta_cxp->id,
                            'codigo' => $cuenta_cxp->codigo,
                            'nombre_cuenta' => $cuenta_cxp->nombre,
                            'concepto' => 'Inventarios compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                            'debe' => $compra->sub_total,
                            'haber' => NULL,
                            'saldo' => 0,
                            'productos' => $productos_compra,
                        ];
                        break;
                    }
                }

                if ($compra->iva > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva->id,
                        'codigo' => $cuenta_iva->codigo,
                        'nombre_cuenta' => $cuenta_iva->nombre,
                        'concepto' => 'Compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                        'debe' => $compra->iva,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($compra->percepcion > 0) {
                    $detalles[] = [
                        'id_cuenta' => $cuenta_iva_retenido->id,
                        'codigo' => $cuenta_iva_retenido->codigo,
                        'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                        'concepto' => 'Compra de mercadería ' . $compra->tipo_documento . '#' . $compra->referencia,
                        'debe' => $compra->percepcion,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

                if ($compra->renta_retenida > 0) {
                    $detalles[] = [
                        'id_cuenta'         => $cuenta_renta_retenida->id,
                        'codigo'            => $cuenta_renta_retenida->codigo,
                        'nombre_cuenta'     => $cuenta_renta_retenida->nombre,
                        'concepto' => 'Compra de mercancía ' . $compra->tipo_documento . ' #' . $compra->referencia,
                        'debe'              => $compra->renta_retenida,
                        'haber'             => NULL,
                        'saldo'             => 0,
                    ];
                }


            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
            // 'ventas' => $ventas,
            // 'abonos_ventas' => $abonos_ventas,
            // 'compras' => $compras,
            // 'abonos_compras' => $abonos_compras,
        ];



        return Response()->json($data, 200);

    }

    public function generarPDF($id)
    {
        $partida = Partida::with('detalles')->where('id', $id)->firstOrFail();

        $pdf = PDF::loadView('contabilidad.partidas.detalle-partida', [
            'partida' => $partida
        ]);

        return $pdf->stream('partida-'. $partida->id . '-' . $partida->correlativo . '.pdf');
    }

    public function delete($id)
    {
        $partida = Partida::findOrFail($id);
        $partida->delete();

        return Response()->json($partida, 201);

    }

    public function cerrarPartidas(CerrarPartidasRequest $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            $cierreMesService = new CierreMesService();

            // Realizar cierre completo del mes
            $resultado = $cierreMesService->cerrarMes(
                $year,
                $month,
                auth()->user()->id,
                auth()->user()->id_empresa
            );

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cerrar el período: ' . $e->getMessage()
            ], 500);
        }
    }

    public function abrirPartida(AbrirPartidaRequest $request)
    {
        try {
            $user = auth()->user();
            if ($user->tipo !== 'Administrador') {
                return response()->json([
                    'error' => 'No tiene permisos para realizar esta acción.'
                ], 403);
            }

            $id = $request->input('id');

            $partida = Partida::find($id);
            if (!$partida) {
                return response()->json([
                    'error' => 'Partida no encontrada.'
                ], 404);
            }

            if ($partida->estado !== 'Cerrada') {
                return response()->json([
                    'error' => 'Solo se pueden abrir partidas cerradas.'
                ], 400);
            }

            $partida->estado = 'Pendiente';
            $partida->save();

            return response()->json([
                'message' => 'Partida abierta exitosamente',
                'partida' => $partida
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al abrir la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reabrirPeriodo(ReabrirPeriodoRequest $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            $cierreMesService = new CierreMesService();

            // Reabrir período
            $resultado = $cierreMesService->reabrirPeriodo(
                $year,
                $month,
                auth()->user()->id_empresa
            );

            return response()->json($resultado);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al reabrir el período: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verificarEstadoPeriodo(VerificarEstadoPeriodoRequest $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            $empresa_id = auth()->user()->id_empresa;

            // Obtener información del período desde la tabla saldos_mensuales
            $saldoMensual = SaldoMensual::where('year', $year)
                ->where('month', $month)
                ->where('id_empresa', $empresa_id)
                ->first();

            $cerrado = false;
            $fechaCierre = null;
            $usuarioCierre = null;

            if ($saldoMensual) {
                $cerrado = $saldoMensual->estado === 'Cerrado';
                $fechaCierre = $saldoMensual->fecha_cierre;

                if ($saldoMensual->id_usuario_cierre) {
                    $usuarioCierre = User::withoutGlobalScopes()
                        ->find($saldoMensual->id_usuario_cierre);
                }
            }

            $response = [
                'periodo' => "{$month}/{$year}",
                'cerrado' => $cerrado,
                'estado' => $cerrado ? 'Cerrado' : 'Abierto'
            ];

            // Agregar información adicional si está cerrado
            if ($cerrado && $fechaCierre) {
                $response['fecha_cierre'] = $fechaCierre;
                $response['usuario_cierre'] = $usuarioCierre ? $usuarioCierre->name : null;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al verificar el estado del período: ' . $e->getMessage()
            ], 500);
        }
    }

      public function obtenerBalanceComprobacion(ObtenerBalanceComprobacionRequest $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          $cierreMesService = new CierreMesService();

          $balance = $cierreMesService->obtenerBalanceComprobacion(
              $year,
              $month,
              auth()->user()->id_empresa
          );

          return response()->json($balance);

      } catch (\Exception $e) {
          return response()->json([
              'error' => 'Error al obtener el balance de comprobación: ' . $e->getMessage()
          ], 500);
      }
  }

  public function simularCierreMes(SimularCierreMesRequest $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          $simulacionService = new SimulacionCierreService();

          $resultadoSimulacion = $simulacionService->simularCierreMes(
              $year,
              $month,
              auth()->user()->id_empresa
          );

          return response()->json($resultadoSimulacion);

      } catch (\Exception $e) {
          return response()->json([
              'error' => 'Error al simular el cierre: ' . $e->getMessage()
          ], 500);
      }
  }

    // Métodos movidos a PartidaService

}
