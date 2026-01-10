<?php

namespace App\Services\Contabilidad\Partidas;

use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Inventario\Categorias\Cuenta as CuentaCategoria;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Abono as AbonoVenta;
use App\Models\Admin\FormaDePago;
use App\Services\Contabilidad\Partidas\PartidaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartidaIngresosService
{
    protected $partidaService;

    public function __construct(PartidaService $partidaService)
    {
        $this->partidaService = $partidaService;
    }

    /**
     * Genera una partida de ingresos basada en ventas y abonos de una fecha
     *
     * @param string $fecha
     * @param int $idUsuario
     * @param int $idEmpresa
     * @return array
     */
    public function generarPartidaIngresos(string $fecha, int $idUsuario, int $idEmpresa): array
    {
        // Aumentar límites para respuestas grandes desde el inicio
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300'); // 5 minutos
        ini_set('post_max_size', '100M');
        ini_set('upload_max_filesize', '100M');

        // Deshabilitar output buffering para respuestas grandes
        if (ob_get_level()) {
            ob_end_clean();
        }
        ini_set('output_buffering', 'Off');
        ini_set('zlib.output_compression', 'Off');

        $startTime = microtime(true);
        Log::info('=== INICIO generarPartidaIngresos ===', [
            'fecha' => $fecha,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'output_buffering' => ini_get('output_buffering')
        ]);

        try {
            // Cargar configuración una sola vez
            $configuracion = Configuracion::first();
            Log::info('Configuración cargada', ['tiempo' => microtime(true) - $startTime]);

            // OPTIMIZACIÓN 1: Eager loading optimizado - solo campos necesarios
            $ventas = Venta::where('estado','!=', 'Anulada')
                        ->where('fecha', $fecha)
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

            Log::info('Ventas cargadas', [
                'cantidad' => $ventas->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // OPTIMIZACIÓN 2: Eager loading optimizado para abonos
            $abonos_ventas = AbonoVenta::where('estado', 'Confirmado')
                        ->where('fecha', $fecha)
                        ->select(['id', 'fecha', 'total', 'forma_pago', 'id_venta', 'id_sucursal'])
                        ->with([
                            'venta' => function($query) {
                                $query->select(['id', 'correlativo', 'nombre_documento']);
                            }
                        ])
                        ->get();

            Log::info('Abonos cargados', [
                'cantidad' => $abonos_ventas->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Preparar datos de ventas y abonos
            $ventas->each->setAttribute('tipo', 'venta');
            $abonos_ventas->each(function ($abono) {
                $abono->tipo = 'abono';
                $abono->nombre_documento = $abono->venta ? $abono->venta->nombre_documento : null;
                $abono->correlativo = $abono->venta ? $abono->venta->correlativo : null;
            });

            $ingresos = $ventas->merge($abonos_ventas);
            Log::info('Ingresos preparados', [
                'total' => $ingresos->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Si no hay ingresos, retornar respuesta vacía
            if ($ingresos->isEmpty()) {
                Log::info('No hay ingresos para la fecha', ['fecha' => $fecha]);
                return [
                    'partida' => [
                        'fecha' => $fecha,
                        'tipo' => 'Ingreso',
                        'concepto' => 'Ingresos por ventas',
                        'estado' => 'Pendiente',
                    ],
                    'detalles' => []
                ];
            }

            // OPTIMIZACIÓN 3: Cargar todas las formas de pago con banco de una vez
            $formasPagoNombres = $ingresos->pluck('forma_pago')->unique()->filter();
            Log::info('Formas de pago únicas', [
                'cantidad' => $formasPagoNombres->count(),
                'nombres' => $formasPagoNombres->toArray()
            ]);

            $formasPago = FormaDePago::select(['id', 'nombre', 'id_banco'])
                        ->with(['banco' => function($query) {
                            $query->select(['id', 'id_cuenta_contable']);
                        }])
                        ->whereIn('nombre', $formasPagoNombres)
                        ->get()
                        ->keyBy('nombre');

            Log::info('Formas de pago cargadas', [
                'cantidad' => $formasPago->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // OPTIMIZACIÓN 4: Cargar todas las cuentas de configuración de una vez
            $cuentasConfigIds = [
                $configuracion->id_cuenta_ventas,
                $configuracion->id_cuenta_iva_ventas,
                $configuracion->id_cuenta_iva_retenido_ventas,
                $configuracion->id_cuenta_costo_venta,
                $configuracion->id_cuenta_inventario,
                $configuracion->id_cuenta_cxc
            ];

            $cuentasConfig = Cuenta::whereIn('id', array_filter($cuentasConfigIds))
                        ->get()
                        ->keyBy('id');

            $cuenta_ventas = $cuentasConfig->get($configuracion->id_cuenta_ventas);
            $cuenta_iva = $cuentasConfig->get($configuracion->id_cuenta_iva_ventas);
            $cuenta_iva_retenido = $cuentasConfig->get($configuracion->id_cuenta_iva_retenido_ventas);
            $cuenta_costos = $cuentasConfig->get($configuracion->id_cuenta_costo_venta);
            $cuenta_inventarios = $cuentasConfig->get($configuracion->id_cuenta_inventario);
            $cuenta_cxc = $cuentasConfig->get($configuracion->id_cuenta_cxc);

            // OPTIMIZACIÓN 5: Cargar todas las cuentas de categorías necesarias de una vez
            $categoriasSucursales = [];
            $totalDetalles = 0;
            foreach ($ventas as $venta) {
                foreach ($venta->detalles as $detalle) {
                    $totalDetalles++;
                    if ($detalle->producto && $detalle->producto->id_categoria && $venta->id_sucursal) {
                        $key = $detalle->producto->id_categoria . '_' . $venta->id_sucursal;
                        if (!isset($categoriasSucursales[$key])) {
                            $categoriasSucursales[$key] = [
                                'id_categoria' => $detalle->producto->id_categoria,
                                'id_sucursal' => $venta->id_sucursal
                            ];
                        }
                    }
                }
            }

            Log::info('Categorías y sucursales recopiladas', [
                'total_detalles' => $totalDetalles,
                'combinaciones_unicas' => count($categoriasSucursales),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Cargar todas las cuentas de categorías de una vez
            $cuentasCategorias = [];
            if (!empty($categoriasSucursales)) {
                $categoriaSucursalIds = array_values($categoriasSucursales);

                $cuentasCategoriasQuery = CuentaCategoria::select([
                    'id', 'id_categoria', 'id_sucursal',
                    'id_cuenta_contable_ingresos',
                    'id_cuenta_contable_costo',
                    'id_cuenta_contable_inventario'
                ])
                ->where(function($query) use ($categoriaSucursalIds) {
                    foreach ($categoriaSucursalIds as $item) {
                        $query->orWhere(function($q) use ($item) {
                            $q->where('id_categoria', $item['id_categoria'])
                              ->where('id_sucursal', $item['id_sucursal']);
                        });
                    }
                })->get();

                foreach ($cuentasCategoriasQuery as $cc) {
                    $key = $cc->id_categoria . '_' . $cc->id_sucursal;
                    $cuentasCategorias[$key] = $cc;
                }

                Log::info('Cuentas de categorías cargadas', [
                    'cantidad' => count($cuentasCategorias),
                    'tiempo' => microtime(true) - $startTime
                ]);
            }

            // OPTIMIZACIÓN 6: Cargar todas las cuentas contables necesarias de una vez
            $cuentasContablesIds = [];
            foreach ($formasPago as $fp) {
                if ($fp->banco && $fp->banco->id_cuenta_contable) {
                    $cuentasContablesIds[] = $fp->banco->id_cuenta_contable;
                }
            }
            foreach ($cuentasCategorias as $cc) {
                if ($cc->id_cuenta_contable_ingresos) $cuentasContablesIds[] = $cc->id_cuenta_contable_ingresos;
                if ($cc->id_cuenta_contable_costo) $cuentasContablesIds[] = $cc->id_cuenta_contable_costo;
                if ($cc->id_cuenta_contable_inventario) $cuentasContablesIds[] = $cc->id_cuenta_contable_inventario;
            }

            $cuentasContablesIds = array_unique(array_filter($cuentasContablesIds));
            Log::info('IDs de cuentas contables recopilados', [
                'cantidad' => count($cuentasContablesIds),
                'tiempo' => microtime(true) - $startTime
            ]);

            $cuentasContables = Cuenta::select(['id', 'codigo', 'nombre'])
                        ->whereIn('id', $cuentasContablesIds)
                        ->get()
                        ->keyBy('id');

            Log::info('Cuentas contables cargadas', [
                'cantidad' => $cuentasContables->count(),
                'tiempo' => microtime(true) - $startTime
            ]);

            // Partida
            $partida = [
                'fecha' => $fecha,
                'tipo' => 'Ingreso',
                'concepto' => 'Ingresos por ventas',
                'estado' => 'Pendiente',
            ];

            // Detalles
            $detalles = [];
            $ingresoIndex = 0;
            $totalIngresos = $ingresos->count();

            Log::info('Iniciando procesamiento de detalles', [
                'total_ingresos' => $totalIngresos,
                'tiempo' => microtime(true) - $startTime
            ]);

            foreach ($ingresos as $ingreso) {
                $ingresoIndex++;
                if ($ingresoIndex % 50 == 0) {
                    Log::info('Procesando ingresos', [
                        'progreso' => "$ingresoIndex/$totalIngresos",
                        'tiempo' => microtime(true) - $startTime
                    ]);
                }

                // Obtener forma de pago del cache
                $formapago = $formasPago->get($ingreso->forma_pago);

                if(!$formapago || !$formapago->banco || !$formapago->banco->id_cuenta_contable){
                    throw new \Exception('La forma de pago ' . $ingreso->forma_pago . ' no tiene cuenta contable. Venta: ' . $ingreso->nombre_documento . ' #' . $ingreso->correlativo);
                }

                // Obtener cuenta del cache
                $cuenta = $cuentasContables->get($formapago->banco->id_cuenta_contable);
                if (!$cuenta) {
                    $cuenta = Cuenta::find($formapago->banco->id_cuenta_contable);
                    if ($cuenta) {
                        $cuentasContables->put($cuenta->id, $cuenta);
                    }
                }

                if (!$cuenta) {
                    throw new \Exception('La cuenta contable no existe. Forma de pago: ' . $ingreso->forma_pago);
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
                    $productos_venta = $ingreso->detalles;

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = $detalle->producto ? $detalle->producto->id_categoria : null;

                        if($id_categoria && $ingreso->id_sucursal){
                            $key = $id_categoria . '_' . $ingreso->id_sucursal;
                            $cuenta_categoria_sucursal = $cuentasCategorias[$key] ?? null;

                            if(!$cuenta_categoria_sucursal){
                                throw new \Exception('La categoria no tiene cuenta contable. Categoria: ' . ($detalle->producto->nombre_categoria ?? 'N/A'));
                            }

                            $cuenta = $cuentasContables->get($cuenta_categoria_sucursal->id_cuenta_contable_ingresos);
                            if (!$cuenta && $cuenta_categoria_sucursal->id_cuenta_contable_ingresos) {
                                $cuenta = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_ingresos);
                                if ($cuenta) {
                                    $cuentasContables->put($cuenta->id, $cuenta);
                                }
                            }

                            if(!$cuenta){
                                throw new \Exception('La categoria no tiene cuenta contable. Categoria: ' . ($detalle->producto->nombre_categoria ?? 'N/A'));
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
                    ];
                }

                // Costo de venta
                if ($ingreso->tipo == 'venta') {
                    // Los detalles ya están cargados con eager loading
                    $productos_venta = $ingreso->detalles;

                    foreach ($productos_venta as $detalle) {
                        $id_categoria = $detalle->producto ? $detalle->producto->id_categoria : null;

                        if($id_categoria && $ingreso->id_sucursal){
                            $key = $id_categoria . '_' . $ingreso->id_sucursal;
                            $cuenta_categoria_sucursal = $cuentasCategorias[$key] ?? null;

                            if(!$cuenta_categoria_sucursal){
                                throw new \Exception('La categoria no tiene cuenta contable. Categoria: ' . ($detalle->producto->nombre_categoria ?? 'N/A'));
                            }

                            $cuenta_costos = $cuentasContables->get($cuenta_categoria_sucursal->id_cuenta_contable_costo);
                            if (!$cuenta_costos && $cuenta_categoria_sucursal->id_cuenta_contable_costo) {
                                $cuenta_costos = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_costo);
                                if ($cuenta_costos) {
                                    $cuentasContables->put($cuenta_costos->id, $cuenta_costos);
                                }
                            }

                            if(!$cuenta_costos){
                                throw new \Exception('La categoria no tiene cuenta de costo contable. Categoria: ' . ($detalle->producto->nombre_categoria ?? 'N/A'));
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_costos->id,
                                'codigo' => $cuenta_costos->codigo,
                                'nombre_cuenta' => $cuenta_costos->nombre,
                                'concepto' => 'Ingreso por costo de ventas ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => $this->partidaService->normalizarDecimal($detalle->costo * $detalle->cantidad),
                                'haber' => NULL,
                                'saldo' => 0,
                            ];

                            $cuenta_inventarios = $cuentasContables->get($cuenta_categoria_sucursal->id_cuenta_contable_inventario);
                            if (!$cuenta_inventarios && $cuenta_categoria_sucursal->id_cuenta_contable_inventario) {
                                $cuenta_inventarios = Cuenta::find($cuenta_categoria_sucursal->id_cuenta_contable_inventario);
                                if ($cuenta_inventarios) {
                                    $cuentasContables->put($cuenta_inventarios->id, $cuenta_inventarios);
                                }
                            }

                            $detalles[] = [
                                'id_cuenta' => $cuenta_inventarios->id,
                                'codigo' => $cuenta_inventarios->codigo,
                                'nombre_cuenta' => $cuenta_inventarios->nombre,
                                'concepto' => 'Inventarios  ' . $ingreso->nombre_documento . '#' . $ingreso->correlativo,
                                'debe' => NULL,
                                'haber' => $this->partidaService->normalizarDecimal($detalle->costo * $detalle->cantidad),
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
            $tiempoTotal = microtime(true) - $startTime;

            Log::info('=== FIN generarPartidaIngresos ===', [
                'fecha' => $fecha,
                'total_ventas' => $ventas->count(),
                'total_abonos' => $abonos_ventas->count(),
                'total_detalles_generados' => $totalDetalles,
                'tiempo_total_segundos' => round($tiempoTotal, 2),
                'tiempo_total_minutos' => round($tiempoTotal / 60, 2),
                'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
            ]);

            // Limpiar relaciones cargadas para liberar memoria
            $ventas = null;
            $abonos_ventas = null;
            $ingresos = null;
            $formasPago = null;
            $cuentasCategorias = null;
            $cuentasContables = null;
            $cuentasConfig = null;

            // Forzar recolección de basura
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Guardar la partida en la base de datos
            DB::beginTransaction();

            try {
                // Crear la partida
                $partidaModel = new Partida;
                $partida['id_usuario'] = $idUsuario;
                $partida['id_empresa'] = $idEmpresa;
                $partidaModel->fill($partida);
                $partidaModel->save();

                // Guardar los detalles
                foreach ($detalles as $det) {
                    $detalle = new Detalle;
                    $detalle->id_partida = $partidaModel->id;
                    $detalle->fill($det);
                    $detalle->save();
                }

                DB::commit();

                Log::info('Partida guardada exitosamente', [
                    'partida_id' => $partidaModel->id,
                    'total_detalles' => count($detalles)
                ]);

                return [
                    'partida_id' => $partidaModel->id,
                    'message' => 'Partida generada exitosamente. Cargando detalles...',
                    'total_detalles' => count($detalles)
                ];

            } catch (\Exception $e) {
                DB::rollback();
                Log::error('Error al guardar partida', [
                    'mensaje' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Si falla al guardar, intentar retornar los datos directamente
                // pero limitando el tamaño de la respuesta
                if (count($detalles) > 500) {
                    throw new \Exception('La partida tiene demasiados detalles (' . count($detalles) . '). Por favor, contacta al administrador.');
                }

                // Si tiene menos de 500 detalles, intentar retornar normalmente
                return [
                    'partida' => $partida,
                    'detalles' => $detalles,
                ];
            }

        } catch (\Exception $e) {
            $tiempoTotal = microtime(true) - $startTime;
            Log::error('Error en generarPartidaIngresos', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'fecha' => $fecha,
                'tiempo_hasta_error' => round($tiempoTotal, 2)
            ]);

            throw $e;
        }
    }
}

