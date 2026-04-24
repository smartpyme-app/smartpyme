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
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Contabilidad\PartidaExcelExport;

class PartidasController extends Controller
{


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
        $totalesGenerales = $this->calcularTotalesGenerales($request);

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
        $totalDetalles = Detalle::where('id_partida', $id)->count();
        
        // Calcular totales desde la base de datos (más eficiente)
        $totales = Detalle::where('id_partida', $id)
            ->selectRaw('COALESCE(SUM(debe), 0) as total_debe, COALESCE(SUM(haber), 0) as total_haber')
            ->first();
        
        // Cargar solo los primeros 100 detalles para evitar problemas de memoria
        $perPage = 100;
        $detalles = Detalle::where('id_partida', $id)
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
        $totalDetalles = Detalle::where('id_partida', $id)->count();
        
        // Calcular última página correctamente
        $lastPage = (int)ceil($totalDetalles / $perPage);
        
        // Obtener detalles paginados
        $detalles = Detalle::where('id_partida', $id)
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
        $todosDetallesBD = Detalle::where('id_partida', $id)
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

    public function store(Request $request)
    {
        $startTime = microtime(true);
        
        \Log::info('=== INICIO store partida ===', [
            'partida_id' => $request->id,
            'total_detalles_recibidos' => count($request->detalles ?? []),
            'solo_modificados' => $request->has('solo_detalles_modificados') && $request->solo_detalles_modificados === true,
            'memoria_inicial_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
        
        // Validar que detalles esté presente y sea un array (puede estar vacío)
        if (!$request->has('detalles') || !is_array($request->detalles)) {
            return Response()->json([
                'error' => ['El campo detalles es obligatorio.'],
                'code' => 422
            ], 422);
        }
        
        $request->validate([
            'fecha'         => 'required|date',
            'tipo'          => 'required|max:255',
            'concepto'      => 'required|max:255',
            'estado'        => 'required|max:255',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            if($request->id)
                $partida = Partida::findOrFail($request->id);
            else
                $partida = new Partida;

            $estadoOriginal = $partida->estado;

            $partida->fill($request->all());
            $partida->save();
            
            \Log::info('Partida guardada', [
                'partida_id' => $partida->id,
                'tiempo_desde_inicio' => microtime(true) - $startTime
            ]);

            if (isset($request->estado)) {
                $estadoNuevo = $request->estado;

                // Si cambió a "Anulada", quitar correlativo
                if ($estadoOriginal !== 'Anulada' && $estadoNuevo === 'Anulada') {
                    $partida->correlativo = null;
                    $partida->save();

                    // Reordenar automáticamente ese mes/tipo
                    $año = date('Y', strtotime($partida->fecha));
                    $mes = date('m', strtotime($partida->fecha));
                    Partida::reordenarCorrelativos($año, $mes, $partida->tipo, $partida->id_empresa);
                }

                // Si cambió de "Anulada" a otro estado, regenerar correlativo
                if ($estadoOriginal === 'Anulada' && $estadoNuevo !== 'Anulada') {
                    // Regenerar correlativo
                    $partida->correlativo = $partida->generarCorrelativo();
                    $partida->save();

                    // Reordenar automáticamente ese mes/tipo
                    $año = date('Y', strtotime($partida->fecha));
                    $mes = date('m', strtotime($partida->fecha));
                    Partida::reordenarCorrelativos($año, $mes, $partida->tipo, $partida->id_empresa);
                }
            }

            // Si solo se están enviando detalles modificados, procesar solo esos
            $soloModificados = $request->has('solo_detalles_modificados') && $request->solo_detalles_modificados === true;
            
            $totalDetalles = count($request->detalles);
            $detallesProcesados = 0;
            
            \Log::info('Iniciando procesamiento de detalles', [
                'partida_id' => $partida->id,
                'total_detalles_a_procesar' => $totalDetalles,
                'solo_modificados' => $soloModificados,
                'tiempo_desde_inicio' => microtime(true) - $startTime
            ]);
            
            foreach ($request->detalles as $index => $det) {
                $detallesProcesados++;
                
                // Log cada 100 detalles para monitorear progreso
                if ($detallesProcesados % 100 == 0) {
                    \Log::info('Procesando detalles', [
                        'progreso' => "$detallesProcesados/$totalDetalles",
                        'tiempo_desde_inicio' => round(microtime(true) - $startTime, 2)
                    ]);
                }
                
                if(isset($det['id'])) {
                    $detalle = Detalle::findOrFail($det['id']);
                    $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                }else {
                    $detalle = new Detalle;
                    $cuenta = Cuenta::findOrFail($det['id_cuenta']);
                }

                // Normalizar valores decimales ANTES de guardar (convertir comas a puntos)
                if (isset($det['debe']) && $det['debe'] !== null && $det['debe'] !== '') {
                    $det['debe'] = $this->normalizeDecimal($det['debe']);
                }
                if (isset($det['haber']) && $det['haber'] !== null && $det['haber'] !== '') {
                    $det['haber'] = $this->normalizeDecimal($det['haber']);
                }

                $detalle['id_partida'] = $partida->id;
                $detalle->fill($det);
                $detalle['codigo'] = $cuenta->codigo;
                $detalle['nombre_cuenta'] = $cuenta->nombre;
                $detalle->save();

                // Valores normalizados para uso posterior
                $debe = $detalle->debe ? $this->normalizeDecimal($detalle->debe) : 0;
                $haber = $detalle->haber ? $this->normalizeDecimal($detalle->haber) : 0;

                // Aplicar partida
                // if(($request['estado'] == 'Aplicada') && ($estadoOriginal != 'Aplicada')){
                //     $detalle->cuenta->increment('cargo', $debe);
                //     $detalle->cuenta->increment('abono', $haber);

                //     if($detalle->cuenta->naturaleza == 'Deudor'){
                //         $detalle->cuenta->increment('saldo', $debe - $haber);
                //     }else{
                //         $detalle->cuenta->increment('saldo', $haber - $debe);
                //     }
                // }

                // Anular aplicacion
                // if(($request['estado'] != 'Aplicada') && ($estadoOriginal == 'Aplicada')){
                //     $detalle->cuenta->decrement('cargo', $debe);
                //     $detalle->cuenta->decrement('abono', $haber);
                //     if($detalle->cuenta->naturaleza == 'Deudor'){
                //         $detalle->cuenta->decrement('saldo', $debe - $haber);
                //     }else{
                //         $detalle->cuenta->decrement('saldo', $haber - $debe);
                //     }
                // }
            }

            \Log::info('Todos los detalles procesados, haciendo commit', [
                'partida_id' => $partida->id,
                'total_detalles_procesados' => $detallesProcesados,
                'tiempo_desde_inicio' => round(microtime(true) - $startTime, 2),
                'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            DB::commit();
            
            \Log::info('=== FIN store partida (EXITOSO) ===', [
                'partida_id' => $partida->id,
                'tiempo_total_segundos' => round(microtime(true) - $startTime, 2),
                'memoria_final_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);
            
            return Response()->json($partida, 200);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('=== ERROR store partida (Exception) ===', [
                'partida_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tiempo_hasta_error' => round(microtime(true) - $startTime, 2)
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            \Log::error('=== ERROR store partida (Throwable) ===', [
                'partida_id' => $request->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tiempo_hasta_error' => round(microtime(true) - $startTime, 2)
            ]);
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
    * Método para reordenar correlativos
    */
    public function reordenarCorrelativos(Request $request)
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

        // Reordenamiento específico por mes/tipo - SOLO validar si NO es 'todos'
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2030',
            'mes' => 'required|integer|min:1|max:12',
            'tipo' => 'required|in:Ingreso,Egreso,Diario,CxC,CxP,Cierre'
        ]);

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

    public function generarIngresos(Request $request)
    {
        $startTime = microtime(true);
        
        // Aumentar límites de PHP para manejar grandes volúmenes de datos
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        ini_set('post_max_size', '100M');
        ini_set('upload_max_filesize', '100M');
        
        \Log::info('=== INICIO generarIngresos ===', [
            'fecha' => $request->fecha,
            'memoria_inicial_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
        ]);
        
        $request->validate([
            'fecha' => 'required|date',
        ]);

        try {
            // Cargar configuración una sola vez
            $configuracion = Configuracion::first();
            \Log::info('Configuración cargada', ['tiempo' => microtime(true) - $startTime]);
            
            // OPTIMIZACIÓN 1: Eager loading optimizado - solo campos necesarios
            // NOTA: nombre_documento es un accessor, no una columna, por eso cargamos la relación 'documento'
            $ventas = Venta::where('estado','!=', 'Anulada')
                        ->where('fecha', $request->fecha)
                        ->where('id_empresa', auth()->user()->id_empresa)
                        ->select(['id', 'fecha', 'correlativo', 'id_documento', 'forma_pago', 
                                 'total', 'sub_total', 'iva', 'iva_retenido', 'total_costo', 
                                 'id_sucursal', 'estado'])
                        ->with([
                            'documento' => function($query) {
                                $query->select(['id', 'nombre']);
                            },
                            'detalles' => function($query) {
                                $query->select(['id', 'id_venta', 'id_producto', 'total', 'costo', 'cantidad']);
                            },
                            'detalles.producto' => function($query) {
                                $query->select(['id', 'id_categoria']);
                            }
                        ])
                        ->get();
            
            \Log::info('Ventas cargadas', [
                'cantidad' => $ventas->count(),
                'tiempo' => microtime(true) - $startTime
            ]);
            
            // OPTIMIZACIÓN 2: Eager loading optimizado para abonos
            $abonos_ventas = AbonoVenta::where('estado', 'Confirmado')
                        ->where('fecha', $request->fecha)
                        ->where('id_empresa', auth()->user()->id_empresa)
                        ->select(['id', 'fecha', 'total', 'forma_pago', 'id_venta', 'id_sucursal'])
                        ->with([
                            'venta' => function($query) {
                                $query->select(['id', 'correlativo', 'id_documento']);
                            },
                            'venta.documento' => function($query) {
                                $query->select(['id', 'nombre']);
                            }
                        ])
                        ->get();

            \Log::info('Abonos cargados', [
                'cantidad' => $abonos_ventas->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Preparar datos de ventas y abonos
            $ventas->each->setAttribute('tipo', 'venta');
            $abonos_ventas->each(function ($abono) {
                $abono->tipo = 'abono';
                $abono->nombre_documento = $abono->venta && $abono->venta->documento ? $abono->venta->documento->nombre : null;
                $abono->correlativo = $abono->venta ? $abono->venta->correlativo : null;
            });

            $ingresos = $ventas->merge($abonos_ventas);
            \Log::info('Ingresos preparados', [
                'total' => $ingresos->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Si no hay ingresos, retornar respuesta vacía
            if ($ingresos->isEmpty()) {
                \Log::info('No hay ingresos para la fecha', ['fecha' => $request->fecha]);
                return Response()->json([
                    'partida' => [
                        'fecha' => $request->fecha,
                        'tipo' => 'Ingreso',
                        'concepto' => 'Ingresos por ventas',
                        'estado' => 'Pendiente',
                    ],
                    'detalles' => []
                ], 200);
            }

            // OPTIMIZACIÓN 3: Cargar todas las formas de pago con banco de una vez
            // El GlobalScope de FormaDePago ya filtra por id_empresa del usuario autenticado
            $formasPagoNombres = $ingresos->pluck('forma_pago')->unique()->filter()->values();
            
            \Log::info('Formas de pago encontradas en ingresos', [
                'nombres' => $formasPagoNombres->toArray(),
                'cantidad' => $formasPagoNombres->count(),
                'id_empresa' => auth()->user()->id_empresa
            ]);
            
            // El GlobalScope ya filtra por empresa, pero asegurémonos explícitamente
            // IMPORTANTE: Cuando usamos select() en eager loading, debemos incluir la clave foránea
            // El problema puede ser que el GlobalScope de Cuenta filtre incorrectamente
            $formasPago = FormaDePago::with(['banco' => function($query) {
                // Asegurarnos de que el banco se cargue correctamente
                // El GlobalScope de Cuenta debería filtrar por empresa automáticamente
                $query->select(['id', 'id_cuenta_contable', 'id_empresa']);
            }])
            ->where('id_empresa', auth()->user()->id_empresa)
            ->whereIn('nombre', $formasPagoNombres->toArray())
            ->select(['id', 'nombre', 'id_banco', 'id_empresa'])
            ->get();
            
            // Verificar y recargar bancos si es necesario
            foreach ($formasPago as $fp) {
                if ($fp->id_banco) {
                    // Si tiene id_banco pero no se cargó la relación, cargarla manualmente
                    if (!$fp->relationLoaded('banco') || !$fp->banco) {
                        // Cargar sin GlobalScope para asegurar que encontremos el banco
                        $banco = \App\Models\Bancos\Cuenta::withoutGlobalScopes()
                            ->where('id', $fp->id_banco)
                            ->where('id_empresa', auth()->user()->id_empresa)
                            ->select(['id', 'id_cuenta_contable', 'id_empresa'])
                            ->first();
                        
                        if ($banco) {
                            $fp->setRelation('banco', $banco);
                        }
                    }
                }
            }
            
            $formasPago = $formasPago->keyBy('nombre');
            
            \Log::info('Formas de pago cargadas desde BD', [
                'cantidad' => $formasPago->count(),
                'formas_pago' => $formasPago->map(function($fp) {
                    return [
                        'nombre' => $fp->nombre,
                        'id_banco' => $fp->id_banco,
                        'tiene_banco' => $fp->banco ? true : false,
                        'id_cuenta_contable' => $fp->banco ? $fp->banco->id_cuenta_contable : null
                    ];
                })->values()->toArray(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // OPTIMIZACIÓN 4: Cargar todas las categorías de productos de una vez
            $categoriasIds = [];
            foreach ($ingresos as $ingreso) {
                if ($ingreso->tipo == 'venta' && isset($ingreso->detalles)) {
                    foreach ($ingreso->detalles as $detalle) {
                        if (isset($detalle->producto) && $detalle->producto->id_categoria) {
                            $categoriasIds[] = $detalle->producto->id_categoria;
                        }
                    }
                }
            }
            $categoriasIds = array_unique($categoriasIds);
            
            // Cargar todas las cuentas de categorías de una vez
            $cuentasCategorias = collect(); // Inicializar como colección vacía
            if (!empty($categoriasIds)) {
                $sucursalesIds = $ingresos->pluck('id_sucursal')->unique()->filter();
                $cuentasCategorias = CuentaCategoria::whereIn('id_categoria', $categoriasIds)
                    ->whereIn('id_sucursal', $sucursalesIds)
                    ->select(['id', 'id_categoria', 'id_sucursal', 'id_cuenta_contable_ingresos', 
                             'id_cuenta_contable_costo', 'id_cuenta_contable_inventario'])
                    ->get()
                    ->keyBy(function($item) {
                        return $item->id_categoria . '_' . $item->id_sucursal;
                    });
            }
            
            \Log::info('Cuentas de categorías cargadas', [
                'cantidad' => count($cuentasCategorias),
                'tiempo' => microtime(true) - $startTime
            ]);

            // OPTIMIZACIÓN 5: Cargar todas las cuentas necesarias de una vez
            $cuentasIds = [
                $configuracion->id_cuenta_ventas,
                $configuracion->id_cuenta_iva_ventas,
                $configuracion->id_cuenta_iva_retenido_ventas,
                $configuracion->id_cuenta_costo_venta,
                $configuracion->id_cuenta_inventario,
                $configuracion->id_cuenta_cxc
            ];
            
            // Agregar IDs de cuentas de formas de pago
            foreach ($formasPago as $fp) {
                if ($fp->banco && $fp->banco->id_cuenta_contable) {
                    $cuentasIds[] = $fp->banco->id_cuenta_contable;
                }
            }
            
            // Agregar IDs de cuentas de categorías
            // keyBy() devuelve una colección indexada, iteramos sobre los valores (modelos individuales)
            foreach ($cuentasCategorias as $key => $cc) {
                // $cc es un modelo individual de CuentaCategoria
                if ($cc && is_object($cc)) {
                    if (isset($cc->id_cuenta_contable_ingresos) && $cc->id_cuenta_contable_ingresos) {
                        $cuentasIds[] = $cc->id_cuenta_contable_ingresos;
                    }
                    if (isset($cc->id_cuenta_contable_costo) && $cc->id_cuenta_contable_costo) {
                        $cuentasIds[] = $cc->id_cuenta_contable_costo;
                    }
                    if (isset($cc->id_cuenta_contable_inventario) && $cc->id_cuenta_contable_inventario) {
                        $cuentasIds[] = $cc->id_cuenta_contable_inventario;
                    }
                }
            }
            
            $cuentasIds = array_unique(array_filter($cuentasIds));
            $cuentas = Cuenta::whereIn('id', $cuentasIds)
                ->select(['id', 'codigo', 'nombre'])
                ->get()
                ->keyBy('id');
            
            \Log::info('Cuentas cargadas', [
                'cantidad' => $cuentas->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas',
                'estado' => 'Pendiente',
            ];

            // Detalles
            $detalles = [];
            
            // Usar cuentas desde el cache
            $cuenta_ventas = $cuentas[$configuracion->id_cuenta_ventas] ?? null;
            $cuenta_iva = $cuentas[$configuracion->id_cuenta_iva_ventas] ?? null;
            $cuenta_iva_retenido = $cuentas[$configuracion->id_cuenta_iva_retenido_ventas] ?? null;
            $cuenta_costos = $cuentas[$configuracion->id_cuenta_costo_venta] ?? null;
            $cuenta_inventarios = $cuentas[$configuracion->id_cuenta_inventario] ?? null;
            $cuenta_cxc = $cuentas[$configuracion->id_cuenta_cxc] ?? null;

            foreach ($ingresos as $ingreso) {
                // Usar forma de pago desde el cache
                $formapago = $formasPago[$ingreso->forma_pago] ?? null;
                
                \Log::info('Verificando forma de pago', [
                    'forma_pago_ingreso' => $ingreso->forma_pago,
                    'formapago_encontrada' => $formapago ? true : false,
                    'tiene_banco' => $formapago && $formapago->banco ? true : false,
                    'id_cuenta_contable' => $formapago && $formapago->banco ? $formapago->banco->id_cuenta_contable : null,
                    'venta' => ($ingreso->nombre_documento ?? 'N/A') . ' #' . ($ingreso->correlativo ?? 'N/A')
                ]);

                if(!$formapago){
                    \Log::error('Forma de pago no encontrada', [
                        'forma_pago_buscada' => $ingreso->forma_pago,
                        'formas_pago_disponibles' => $formasPago->keys()->toArray()
                    ]);
                    return  Response()->json(['titulo' => 'La forma de pago ' . $ingreso->forma_pago . ' no fue encontrada.', 'error' => 'Venta: ' . ($ingreso->nombre_documento ?? 'N/A') . ' #' . ($ingreso->correlativo ?? 'N/A'), 'code' => 400], 400);
                }
                
                if(!$formapago->banco){
                    \Log::error('Forma de pago sin banco', [
                        'forma_pago' => $formapago->nombre,
                        'id_banco' => $formapago->id_banco
                    ]);
                    return  Response()->json(['titulo' => 'La forma de pago ' . $ingreso->forma_pago . ' no tiene banco asociado.', 'error' => 'Venta: ' . ($ingreso->nombre_documento ?? 'N/A') . ' #' . ($ingreso->correlativo ?? 'N/A'), 'code' => 400], 400);
                }
                
                if(!$formapago->banco->id_cuenta_contable){
                    \Log::error('Banco sin cuenta contable', [
                        'forma_pago' => $formapago->nombre,
                        'id_banco' => $formapago->banco->id
                    ]);
                    return  Response()->json(['titulo' => 'La forma de pago ' . $ingreso->forma_pago . ' no tiene cuenta contable.', 'error' => 'Venta: ' . ($ingreso->nombre_documento ?? 'N/A') . ' #' . ($ingreso->correlativo ?? 'N/A'), 'code' => 400], 400);
                }

                // Usar cuenta desde el cache
                $cuenta = $cuentas[$formapago->banco->id_cuenta_contable] ?? null;
                
                if (!$cuenta) {
                    return Response()->json(['titulo' => 'La cuenta contable no existe.', 'error' => 'Forma de pago: ' . $ingreso->forma_pago, 'code' => 400], 400);
                }

                $detalles[] = [
                    'id_cuenta' => $cuenta->id,
                    'codigo' => $cuenta->codigo,
                    'nombre_cuenta' => $cuenta->nombre,
                    'concepto' => 'Ingresos por ' . $ingreso->tipo . ' ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                    'debe' => $ingreso->total,
                    'haber' => NULL,
                    'saldo' => 0,
                ];

                if($ingreso->tipo == 'venta'){
                    // Los detalles ya están cargados con eager loading
                    $productos_venta = $ingreso->detalles ?? collect();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            // Usar cuenta de categoría desde el cache
                            $key = $id_categoria . '_' . $ingreso->id_sucursal;
                            $cuenta_categoria_sucursal = $cuentasCategorias[$key] ?? null;

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria ID: ' . $id_categoria . ' Sucursal: ' . $ingreso->id_sucursal, 'code' => 400], 400);
                            }

                            // Usar cuenta desde el cache
                            $cuenta = $cuentas[$cuenta_categoria_sucursal->id_cuenta_contable_ingresos] ?? null;

                            if(!$cuenta){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria ID: ' . $id_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta->id,
                                'codigo' => $cuenta->codigo,
                                'nombre_cuenta' => $cuenta->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $detalle->total,
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_ventas->id,
                                'codigo' => $cuenta_ventas->codigo,
                                'nombre_cuenta' => $cuenta_ventas->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $ingreso->sub_total,
                                'saldo' => 0,
                                'productos' => $productos_venta,
                            ];
                            break;
                        }
                    }

                    if ($ingreso->iva > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva->id,
                            'codigo' => $cuenta_iva->codigo,
                            'nombre_cuenta' => $cuenta_iva->nombre,
                            'concepto' => '  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                            'debe' => NULL,
                            'haber' => $ingreso->iva,
                            'saldo' => 0,
                        ];
                    }

                    if ($ingreso->iva_retenido > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva_retenido->id,
                            'codigo' => $cuenta_iva_retenido->codigo,
                            'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                            'concepto' => '  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                            'debe' => $ingreso->iva_retenido,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }
                }
                else{
                    $detalles[] = [
                        'id_cuenta' => $cuenta_cxc->id,
                        'codigo' => $cuenta_cxc->codigo,
                        'nombre_cuenta' => $cuenta_cxc->nombre,
                        'concepto' => 'Ingreso por abono ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                        'debe' => NULL,
                        'haber' => $ingreso->total,
                        'saldo' => 0,
                        'productos' => $productos_venta,
                    ];
                }


                // Costo de venta
                if ($ingreso->tipo == 'venta') {
                    // Los detalles ya están cargados con eager loading
                    $productos_venta = $ingreso->detalles ?? collect();

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            // Usar cuenta de categoría desde el cache
                            $key = $id_categoria . '_' . $ingreso->id_sucursal;
                            $cuenta_categoria_sucursal = $cuentasCategorias[$key] ?? null;

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria ID: ' . $id_categoria . ' Sucursal: ' . $ingreso->id_sucursal, 'code' => 400], 400);
                            }

                            // Usar cuenta desde el cache
                            $cuenta_costos = $cuentas[$cuenta_categoria_sucursal->id_cuenta_contable_costo] ?? null;

                            if(!$cuenta_costos){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta de costo contable.', 'error' => 'Categoria ID: ' . $id_categoria, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => $this->normalizeDecimal($detalle->costo * $detalle->cantidad),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];

                            // Usar cuenta desde el cache
                            $cuenta_inventarios = $cuentas[$cuenta_categoria_sucursal->id_cuenta_contable_inventario] ?? null;
                            
                            if (!$cuenta_inventarios) {
                                return Response()->json(['titulo' => 'La categoria no tiene cuenta de inventario contable.', 'error' => 'Categoria ID: ' . $id_categoria, 'code' => 400], 400);
                            }
                            
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $this->normalizeDecimal($detalle->costo * $detalle->cantidad),
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => $ingreso->total_costo,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $ingreso->total_costo,
                                'saldo' => 0,
                            ];
                        }
                    }
                }

            }

            $totalDetalles = count($detalles);
            \Log::info('Detalles generados', [
                'total_detalles' => $totalDetalles,
                'tiempo' => microtime(true) - $startTime,
                'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            // Guardar partida y detalles en BD dentro de una transacción
            DB::beginTransaction();
            try {
                $partidaModel = new Partida($partida);
                $partidaModel->id_usuario = auth()->user()->id;
                $partidaModel->id_empresa = auth()->user()->id_empresa;
                $partidaModel->correlativo = $partidaModel->generarCorrelativo();
                $partidaModel->save();

                foreach ($detalles as $det) {
                    $detalleModel = new Detalle($det);
                    $detalleModel->id_partida = $partidaModel->id;
                    $detalleModel->save();
                }

                DB::commit();
                \Log::info('Partida y detalles guardados en BD', [
                    'partida_id' => $partidaModel->id,
                    'total_detalles' => $totalDetalles,
                    'tiempo_total' => round(microtime(true) - $startTime, 2),
                    'memoria_final_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);

                return Response()->json([
                    'message' => 'Partida generada y guardada exitosamente.',
                    'partida_id' => $partidaModel->id,
                    'total_detalles' => $totalDetalles
                ], 200);

            } catch (\Exception $e) {
                DB::rollback();
                \Log::error('Error al guardar partida en BD', [
                    'mensaje' => $e->getMessage(),
                    'archivo' => $e->getFile(),
                    'linea' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'fecha' => $request->fecha,
                    'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);

                // Fallback: Si el error no es por tamaño y los detalles no son excesivos, intentar retornar directamente
                if ($totalDetalles < 500) {
                    \Log::warning('Intentando retornar detalles directamente como fallback', ['total_detalles' => $totalDetalles]);
                    return Response()->json([
                        'partida' => $partida,
                        'detalles' => $detalles,
                        'error_fallback' => 'Error al guardar en BD, pero se retornan los datos directamente.'
                    ], 200);
                } else {
                    return Response()->json([
                        'error' => 'Error al generar ingresos: ' . $e->getMessage(),
                        'message' => 'Error al generar la partida. Demasiados detalles para retornar directamente. Intenta con menos registros o contacta al administrador.',
                        'code' => 413
                    ], 413);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error general en generarIngresos', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Response()->json([
                'error' => 'Error al generar ingresos: ' . $e->getMessage(),
                'code' => 500
            ], 500);
        }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarCxC(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        // Fecha del documento de venta (no la del abono). whereDate evita fallos si el campo es datetime.
        $ventas = Venta::where('estado', 'Pendiente')
            ->whereDate('fecha', $request->fecha)
            ->get();

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
            $cuenta_ventas_general = $configuracion->id_cuenta_ventas
                ? Cuenta::where('id', $configuracion->id_cuenta_ventas)->first()
                : null;

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
                            'concepto' => 'Ingresos por ventas ' . $venta->nombre_documento . ' #' . $venta->correlativo,
                            'debe' => NULL,
                            'haber' => $detalle->total,
                            'saldo' => 0,
                        ];
                    }else{
                        if (!$cuenta_ventas_general) {
                            return Response()->json(['titulo' => 'No hay cuenta contable.', 'error' => 'No está configurada la cuenta general de ventas (productos sin categoría).', 'code' => 400], 400);
                        }
                        $detalles[] = [
                            'id_cuenta' => $cuenta_ventas_general->id,
                            'codigo' => $cuenta_ventas_general->codigo,
                            'nombre_cuenta' => $cuenta_ventas_general->nombre,
                            'concepto' => 'Ingresos por ventas ' . $venta->nombre_documento . ' #' . $venta->correlativo,
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

                // Costo de ventas: en generación individual va en una partida aparte (VentasService, 2.ª partida).
                // No mezclar aquí para igualar criterio y evitar duplicar líneas en un solo asiento CxC.

            }

        // Cobros del día: abonos confirmados (fecha del abono), sin partida contable aún (misma lógica que CXCService).
        $abonosVentasHoy = AbonoVenta::query()
            ->whereDate('fecha', $request->fecha)
            ->where('estado', 'Confirmado')
            ->get();

        foreach ($abonosVentasHoy as $abono) {
            if (Partida::existeParaDocumentoOrigen('Abono de Venta', $abono->id)) {
                continue;
            }
            $formapago = FormaDePago::with('banco')->where('nombre', $abono->forma_pago)->first();
            if (! $formapago || ! $formapago->banco || ! $formapago->banco->id_cuenta_contable) {
                return Response()->json([
                    'titulo' => 'Forma de pago sin cuenta contable',
                    'error' => 'Configure la cuenta del banco para: '.$abono->forma_pago.' (abono venta #'.$abono->id.')',
                    'code' => 400,
                ], 400);
            }
            $cuenta_forma_pago = Cuenta::find($formapago->banco->id_cuenta_contable);
            if (! $cuenta_forma_pago) {
                return Response()->json([
                    'titulo' => 'Cuenta contable no encontrada',
                    'error' => 'Forma de pago: '.$abono->forma_pago,
                    'code' => 400,
                ], 400);
            }

            $ventaRef = Venta::find($abono->id_venta);
            $refTxt = $ventaRef
                ? (($ventaRef->nombre_documento ?? 'Documento').' #'.($ventaRef->correlativo ?? ''))
                : ('Venta #'.$abono->id_venta);

            $detalles[] = [
                'id_cuenta' => $cuenta_forma_pago->id,
                'codigo' => $cuenta_forma_pago->codigo,
                'nombre_cuenta' => $cuenta_forma_pago->nombre,
                'concepto' => 'Abono a cuenta por cobrar '.$refTxt,
                'debe' => $abono->total,
                'haber' => null,
                'saldo' => 0,
            ];
            $detalles[] = [
                'id_cuenta' => $cuenta_cxc->id,
                'codigo' => $cuenta_cxc->codigo,
                'nombre_cuenta' => $cuenta_cxc->nombre,
                'concepto' => 'Abono a cuenta por cobrar '.$refTxt,
                'debe' => null,
                'haber' => $abono->total,
                'saldo' => 0,
            ];
        }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarEgresos(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pagada')
                            ->where('fecha', $request->fecha)->get();
        $abonos_compras = AbonoCompra::where('estado', 'Confirmado')
                            ->where('fecha', $request->fecha)->with('compra')->get();

        $compras->each->setAttribute('tipo', 'compra');
        // $abonos_compras->each->setAttribute('tipo', 'abono');

        $abonos_compras->each(function ($abono) {
            $abono->tipo = 'abono';
            $abono->tipo_documento = $abono->compra ? $abono->compra->tipo_documento : null;
            $abono->referencia = $abono->compra ? $abono->compra->referencia : null;
        });

        $egresos = $compras->merge($abonos_compras);

        // Partida
            $partida = [
                'fecha' => $request->fecha,
                'tipo' => 'Egreso',
                'concepto' => 'Compra de mercancía',
                'estado' => 'Pendiente',
            ];

        // Detalles

            $detalles = [];
            $cuenta_compras = Cuenta::where('id', $configuracion->id_cuenta_compras)->first();
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
            $cuenta_costos = Cuenta::where('id', $configuracion->id_cuenta_costo_compra)->first();
            $cuenta_inventarios = Cuenta::where('id', $configuracion->id_cuenta_inventario)->first();
            $cuenta_renta_retenida = Cuenta::where('id', $configuracion->id_cuenta_renta_retenida_compras)->firstOrFail();
            $cuenta_cxp = Cuenta::where('id', $configuracion->id_cuenta_cxp)->firstOrFail();

            foreach ($egresos as $egreso) {

                $formapago = FormaDePago::with('banco')->where('nombre', $egreso->forma_pago)->first();

                if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                    return  Response()->json(['titulo' => 'La forma de pago ' . $venta->forma_pago . ' no tiene cuenta contable.', 'error' => 'Venta: ' . $venta->nombre_documento . ' #' . $venta->correlativo, 'code' => 400], 400);
                }

                $cuenta = Cuenta::where('id', $formapago->banco->id_cuenta_contable)->first();

                $detalles[] = [
                    'id_cuenta'         => $cuenta->id,
                    'codigo'            => $cuenta->codigo,
                    'nombre_cuenta'     => $cuenta->nombre,
                    'concepto' => 'Egresos por ' . $egreso->tipo . ' ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                    'debe'              => NULL,
                    'haber'             => $egreso->total,
                    'saldo'             => 0
                ];

                if($egreso->tipo == 'compra'){

                    $productos_compra = DetalleCompra::with('producto')->where('id_compra', $egreso->id)->get();

                    foreach ($productos_compra as $detalle) {
                        $id_categoria = isset($detalle->producto) ? $detalle->producto->id_categoria : null;
                        if($id_categoria){
                            $cuenta_categoria_sucursal = CuentaCategoria::where('id_categoria', $id_categoria)->where('id_sucursal', $egreso->id_sucursal)->first();

                            if(!$cuenta_categoria_sucursal){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo, 'code' => 400], 400);
                            }

                            $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();

                            if(!$cuenta){
                                return  Response()->json(['titulo' => 'La categoria no tiene cuenta contable.', 'error' => 'Categoria: ' . $detalle->producto->nombre_categoria . '#' . $egreso->correlativo, 'code' => 400], 400);
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta->id,
                                'codigo' => $cuenta->codigo,
                                'nombre_cuenta' => $cuenta->nombre,
                                'concepto' => 'Compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                                'debe' => $detalle->total,
                                'haber' => NULL,
                                'saldo' => 0,
                            ];
                        }else{
                            $detalles[] = [
                                'id_cuenta' => $cuenta_compras->id,
                                'codigo' => $cuenta_compras->codigo,
                                'nombre_cuenta' => $cuenta_compras->nombre,
                                'concepto' => 'Inventarios compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                                'debe' => $egreso->sub_total,
                                'haber' => NULL,
                                'saldo' => 0,
                                'productos' => $productos_compra,
                            ];
                            break;
                        }
                    }

                    if ($egreso->iva > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva->id,
                            'codigo' => $cuenta_iva->codigo,
                            'nombre_cuenta' => $cuenta_iva->nombre,
                            'concepto' => 'Compra de mercadería ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                            'debe' => $egreso->iva,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }

                    if ($egreso->percepcion > 0) {
                        $detalles[] = [
                            'id_cuenta' => $cuenta_iva_retenido->id,
                            'codigo' => $cuenta_iva_retenido->codigo,
                            'nombre_cuenta' => $cuenta_iva_retenido->nombre,
                            'concepto' => 'Compra de mercadería ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                            'debe' => $egreso->percepcion,
                            'haber' => NULL,
                            'saldo' => 0,
                        ];
                    }

                    if ($egreso->renta_retenida > 0) {
                        $detalles[] = [
                            'id_cuenta'         => $cuenta_renta_retenida->id,
                            'codigo'            => $cuenta_renta_retenida->codigo,
                            'nombre_cuenta'     => $cuenta_renta_retenida->nombre,
                            'concepto' => 'Compra de mercancía ' . $egreso->tipo_documento . ' #' . $egreso->referencia,
                            'debe'              => $egreso->renta_retenida,
                            'haber'             => NULL,
                            'saldo'             => 0,
                        ];
                    }

                }
                else{
                    $detalles[] = [
                        'id_cuenta' => $cuenta_cxp->id,
                        'codigo' => $cuenta_cxp->codigo,
                        'nombre_cuenta' => $cuenta_cxp->nombre,
                        'concepto' => 'Egreso por cxp ' . $egreso->tipo_documento . '#' . $egreso->referencia,
                        'debe' => $egreso->total,
                        'haber' => NULL,
                        'saldo' => 0,
                    ];
                }

            }

        $data = [
            'partida' => $partida,
            'detalles' => $detalles,
        ];



        return Response()->json($data, 200);

    }

    public function generarCxP(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $configuracion = Configuracion::first();
        $compras = Compra::where('estado', 'Pendiente')
            ->whereDate('fecha', $request->fecha)
            ->get();

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
            if (! $cuenta_cxp) {
                return Response()->json(['titulo' => 'No hay cuenta contable.', 'error' => 'No está configurada la cuenta de cuentas por pagar.', 'code' => 400], 400);
            }
            $cuenta_iva = Cuenta::where('id', $configuracion->id_cuenta_iva_compras)->first();
            $cuenta_iva_retenido = Cuenta::where('id', $configuracion->id_cuenta_iva_retenido_compras)->first();
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

                        $cuenta = Cuenta::where('id', $cuenta_categoria_sucursal->id_cuenta_contable_inventario)->first();

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
                            'id_cuenta' => $cuenta_inventarios->id,
                            'codigo' => $cuenta_inventarios->codigo,
                            'nombre_cuenta' => $cuenta_inventarios->nombre,
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

        // Pagos del día: abonos a compras confirmados (fecha del abono), sin partida contable aún (misma lógica que CXPService).
        $abonosCompraHoy = AbonoCompra::query()
            ->whereDate('fecha', $request->fecha)
            ->where('estado', 'Confirmado')
            ->get();

        foreach ($abonosCompraHoy as $abono) {
            if (Partida::existeParaDocumentoOrigen('Abono de Compra', $abono->id)) {
                continue;
            }
            $formapago = FormaDePago::with('banco')->where('nombre', $abono->forma_pago)->first();
            if (! $formapago || ! $formapago->banco || ! $formapago->banco->id_cuenta_contable) {
                return Response()->json([
                    'titulo' => 'Forma de pago sin cuenta contable',
                    'error' => 'Configure la cuenta del banco para: '.$abono->forma_pago.' (abono compra #'.$abono->id.')',
                    'code' => 400,
                ], 400);
            }
            $cuenta_forma_pago = Cuenta::find($formapago->banco->id_cuenta_contable);
            if (! $cuenta_forma_pago) {
                return Response()->json([
                    'titulo' => 'Cuenta contable no encontrada',
                    'error' => 'Forma de pago: '.$abono->forma_pago,
                    'code' => 400,
                ], 400);
            }

            $compraRef = Compra::find($abono->id_compra);
            $refTxt = $compraRef
                ? (($compraRef->tipo_documento ?? 'Documento').' #'.($compraRef->referencia ?? ''))
                : ('Compra #'.$abono->id_compra);

            $detalles[] = [
                'id_cuenta' => $cuenta_cxp->id,
                'codigo' => $cuenta_cxp->codigo,
                'nombre_cuenta' => $cuenta_cxp->nombre,
                'concepto' => 'Abono a cuenta por pagar '.$refTxt,
                'debe' => $abono->total,
                'haber' => null,
                'saldo' => 0,
            ];
            $detalles[] = [
                'id_cuenta' => $cuenta_forma_pago->id,
                'codigo' => $cuenta_forma_pago->codigo,
                'nombre_cuenta' => $cuenta_forma_pago->nombre,
                'concepto' => 'Abono a cuenta por pagar '.$refTxt,
                'debe' => null,
                'haber' => $abono->total,
                'saldo' => 0,
            ];
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

    /**
     * Genera y descarga la partida en formato Excel
     */
    public function generarExcel($id)
    {
        $partida = Partida::with('detalles')->where('id', $id)->firstOrFail();
        $filename = 'partida-' . $partida->id . '-' . ($partida->correlativo ?? 'sin-correlativo') . '.xlsx';

        return Excel::download(new PartidaExcelExport($partida), $filename);
    }

    public function delete($id)
    {
        $partida = Partida::findOrFail($id);
        $partida->delete();

        return Response()->json($partida, 201);

    }

    public function cerrarPartidas(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            // Validar que el mes y año sean válidos
            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

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

    public function abrirPartida(Request $request)
    {
        try {
            $user = auth()->user();
            if ($user->tipo !== 'Administrador') {
                return response()->json([
                    'error' => 'No tiene permisos para realizar esta acción.'
                ], 403);
            }

            $id = $request->input('id');
            if (!$id) {
                return response()->json([
                    'error' => 'ID de partida no proporcionado.'
                ], 400);
            }

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

    public function reabrirPeriodo(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            // Validar que el mes y año sean válidos
            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

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

    public function verificarEstadoPeriodo(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            if (!$month || !$year || $month < 1 || $month > 12) {
                return response()->json([
                    'error' => 'Mes y año inválidos'
                ], 400);
            }

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

      public function obtenerBalanceComprobacion(Request $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          if (!$month || !$year || $month < 1 || $month > 12) {
              return response()->json([
                  'error' => 'Mes y año inválidos'
              ], 400);
          }

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

  public function simularCierreMes(Request $request)
  {
      try {
          $month = $request->input('month');
          $year = $request->input('year');

          if (!$month || !$year || $month < 1 || $month > 12) {
              return response()->json([
                  'error' => 'Mes y año inválidos'
              ], 400);
          }

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

    /**
     * Calcular totales generales con los mismos filtros aplicados
     */
    private function calcularTotalesGenerales(Request $request)
    {
        $query = DB::table('partidas')
            ->leftJoin('partida_detalles', 'partidas.id', '=', 'partida_detalles.id_partida')
            ->where('partidas.id_empresa', auth()->user()->id_empresa);


        if (!$request->has('incluir_anuladas') ||
            $request->incluir_anuladas === false ||
            $request->incluir_anuladas === 'false' ||
            $request->incluir_anuladas === '0') {
            $query->where('partidas.estado', '!=', 'Anulada');
        }

        // Aplicar los mismos filtros que en index
        $query->when($request->buscador, function($q) use ($request){
            return $q->where(function($subQ) use ($request) {
                $subQ->where('partidas.concepto', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.tipo', 'like' ,'%' . $request->buscador . '%')
                     ->orWhere('partidas.correlativo', 'like' ,'%' . $request->buscador . '%');
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

        return $query->selectRaw('
            COALESCE(SUM(partida_detalles.debe), 0) as gran_total_debe,
            COALESCE(SUM(partida_detalles.haber), 0) as gran_total_haber,
            COUNT(DISTINCT partidas.id) as total_registros_filtrados
        ')->first();
    }

    /**
     * Normalizar valores decimales: convertir comas a puntos
     * Para evitar errores de sintaxis SQL con formatos de números europeos
     */
    private function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // Convertir a string y reemplazar comas por puntos
        $normalized = str_replace(',', '.', (string)$value);

        // Convertir a float y luego formatear con 2 decimales usando punto
        return number_format((float)$normalized, 2, '.', '');
    }

}
