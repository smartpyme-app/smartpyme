<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\SuscripcionesExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use App\Models\Admin\Documento;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Impuesto;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JWTAuth;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Admin\Suscripciones\CreateSuscriptionRequest;
use App\Http\Requests\Admin\Suscripciones\EditSuscriptionRequest;

class SuscripcionesController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = Empresa::with(['suscripcion', 'suscripcion.plan' => function ($query) {
                $query->select('id', 'nombre', 'precio', 'slug', 'duracion_dias');
            }]);

            if ($request->estado) {
                if ($request->estado === 'sin_suscripcion') {
                    $query->doesntHave('suscripcion');
                } else {
                    $query->whereHas('suscripcion', function ($q) use ($request) {
                        $q->where('estado', $request->estado);
                    });
                }
            }

            // Resto de filtros
            $query->when($request->buscador, function ($q) use ($request) {
                return $q->where(function ($query) use ($request) {
                    $query->where('nombre', 'like', '%' . $request->buscador . '%')
                        ->orWhere('nombre_propietario', 'like', '%' . $request->buscador . '%');
                });
            })
                ->when($request->suscripcion_inicio, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('created_at', '>=', $request->suscripcion_inicio);
                    });
                })
                ->when($request->suscripcion_fin, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('created_at', '<=', $request->suscripcion_fin);
                    });
                })
                ->when($request->pago_inicio, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('fecha_ultimo_pago', '>=', $request->pago_inicio);
                    });
                })
                ->when($request->pago_fin, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('fecha_ultimo_pago', '<=', $request->pago_fin);
                    });
                })
                ->when($request->fecha_pago_inicio, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('fecha_proximo_pago', '>=', $request->fecha_pago_inicio);
                    });
                })
                ->when($request->fecha_pago_fin, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->whereDate('fecha_proximo_pago', '<=', $request->fecha_pago_fin);
                    });
                })
                ->when($request->plan, function ($q) use ($request) {
                    return $q->whereHas('suscripcion.plan', function ($query) use ($request) {
                        $query->where('nombre', $request->plan);
                    });
                })
                ->when($request->forma_pago, function ($q) use ($request) {
                    return $q->whereHas('suscripcion', function ($query) use ($request) {
                        $query->where('metodo_pago', $request->forma_pago);
                    });
                })
                ->when($request->campania, function ($q) use ($request) {
                    return $q->where('campania', 'like', '%' . $request->campania . '%');
                });

            if (!$request->estado) {
                $query->withCount('suscripcion');
            }

            $orden = $request->orden ?? 'created_at';
            $direccion = $request->direccion ?? 'desc';

            $query = $this->ordenamiento($query, $orden, $direccion);

            $empresas = $query->paginate($request->paginate ?? 10);

            $empresas->through(function ($empresa) {
                if ($empresa->suscripcion && $empresa->suscripcion->plan) {
                    $empresa->suscripcion->plan->tipo_plan = $empresa->suscripcion->plan->getTipoPlanAttribute();
                }
                return $empresa;
            });

            return response()->json($empresas, 200);
        } catch (\Exception $e) {
            Log::error('Error en SuscripcionesController@index: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener datos'], 500);
        }
    }

    public function list()
    {
        $suscripciones = Suscripcion::with(['empresa', 'plan'])
            ->where('estado', 'activo')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($suscripciones, 200);
    }

    public function read($id)
    {
        $suscripcion = Suscripcion::with(['empresa', 'plan', 'usuario'])
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($suscripcion, 200);
    }

    public function ordenamiento($query, $orden, $direccion)
    {
        switch ($orden) {
            case 'estado_suscripcion':
                $query->leftJoin('suscripciones as s1', 'empresas.id', '=', 's1.empresa_id')
                    ->orderBy('s1.estado', $direccion)
                    ->select('empresas.*');
                break;

            case 'estado_pago':
                $query->leftJoin('suscripciones as s2', 'empresas.id', '=', 's2.empresa_id')
                    ->orderBy('s2.estado_ultimo_pago', $direccion)
                    ->select('empresas.*');
                break;

            case 'fecha_proximo_pago':
                $query->leftJoin('suscripciones as s3', 'empresas.id', '=', 's3.empresa_id')
                    ->orderBy('s3.fecha_proximo_pago', $direccion)
                    ->select('empresas.*');
                break;

            case 'fecha_ultimo_pago':
                $query->leftJoin('suscripciones as s4', 'empresas.id', '=', 's4.empresa_id')
                    ->orderBy('s4.fecha_ultimo_pago', $direccion)
                    ->select('empresas.*');
                break;

            case 'plan':
                $query->leftJoin('suscripciones as s5', 'empresas.id', '=', 's5.empresa_id')
                    ->leftJoin('planes as p1', 's5.plan_id', '=', 'p1.id')
                    ->orderBy('p1.nombre', $direccion)
                    ->select('empresas.*');
                break;

            case 'tipo_plan':
                $query->leftJoin('suscripciones as s6', 'empresas.id', '=', 's6.empresa_id')
                    ->orderBy('s6.tipo_plan', $direccion)
                    ->select('empresas.*');
                break;

            case 'metodo_pago':
                $query->orderBy('metodo_pago', $direccion);
                break;

            default:
                $query->orderBy($orden, $direccion);
                break;
        }

        return $query;
    }

    public function createSuscription(CreateSuscriptionRequest $request)
    {
        try {
            $validated = $request->validated();

            $existingSuscripcion = Suscripcion::where('empresa_id', $request->empresa_id)
                ->whereIn('estado', ['Activo', 'En prueba'])
                ->first();

            if ($existingSuscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'La empresa ya tiene una suscripción activa'
                ], 422);
            }

            // Crear nueva suscripción
            $suscripcion = new Suscripcion();
            $suscripcion->empresa_id = $validated['empresa_id'];
            $suscripcion->plan_id = $validated['plan_id'];
            $suscripcion->usuario_id = $validated['usuario_id'];
            $suscripcion->tipo_plan = $validated['tipo_plan'];
            $suscripcion->estado = $validated['estado'];
            $suscripcion->estado_ultimo_pago = $request->input('estado_ultimo_pago');
            $suscripcion->fecha_ultimo_pago = $request->input('estado_ultimo_pago') === config('constants.ESTADO_ORDEN_PAGO_COMPLETADO') ? now() : null;
            $suscripcion->monto = $validated['monto'];
            $suscripcion->fecha_proximo_pago = $validated['fecha_proximo_pago'];
            $suscripcion->fin_periodo_prueba = $validated['fin_periodo_prueba'];

            if ($request->requiere_factura) {
                $suscripcion->requiere_factura = true;
                $suscripcion->nit = $request->nit;
                $suscripcion->nombre_factura = $request->nombre_factura;
                $suscripcion->direccion_factura = $request->direccion_factura;
            }

            if ($validated['estado'] === 'Cancelado') {
                $suscripcion->motivo_cancelacion = $request->motivo_cancelacion;
                $suscripcion->fecha_cancelacion = now();
            }

            if ($request->has('comentarios')) {
                $suscripcion->comentarios = $request->input('comentarios');
            }

            $suscripcion->intentos_cobro = 0;
            $suscripcion->estado_ultimo_pago = 'Pendiente';

            $suscripcion->save();

            // Actualizar campos monto_mensual y monto_anual en la empresa
            $empresa = Empresa::findOrFail($validated['empresa_id']);
            if ($request->has('monto_mensual')) {
                $empresa->monto_mensual = $request->input('monto_mensual');
            }
            if ($request->has('monto_anual')) {
                $empresa->monto_anual = $request->input('monto_anual');
            }
            // Actualizar frecuencia_pago si viene en el request
            if ($request->has('frecuencia_pago')) {
                $empresa->frecuencia_pago = $request->input('frecuencia_pago');
            }
            $empresa->save();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción creada exitosamente',
                'data' => $suscripcion->load('plan', 'empresa')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creando suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creando la suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editSuscription(EditSuscriptionRequest $request)
    {
        try {
            $validated = $request->validated();

            $suscripcion = Suscripcion::findOrFail($validated['id']);

            if ($request->input('plan.id') != $suscripcion->plan_id) {
                $plan = Plan::findOrFail($request->input('plan.id'));
                $suscripcion->plan_id = $plan->id;
                $suscripcion->monto = $plan->precio;
                $suscripcion->tipo_plan = $plan->getTipoPlanAttribute();
                $suscripcion->save();

                $empresa = Empresa::findOrFail($suscripcion->usuario->empresa->id);
                $empresa->plan = $plan->nombre;
                $empresa->tipo_plan = $plan->getTipoPlanAttribute();
                $empresa->save();
            }

            // fecha_proximo_pago no se edita aquí: solo vía «Pago recibido» o integraciones.
            $datosActualizacion = [
                'estado_ultimo_pago' => $request->input('estado_ultimo_pago'),
                'estado' => $validated['estado'],
                'usuario_id' => $validated['usuario_id'],
                'monto' => $validated['monto'],
                'fin_periodo_prueba' => $validated['fin_periodo_prueba'],
                'nit' => $request->input('nit'),
                'nombre_factura' => $request->input('nombre_factura'),
                'direccion_factura' => $request->input('direccion_factura'),
                'motivo_cancelacion' => $request->input('motivo_cancelacion'),
            ];
            if ($request->exists('comentarios')) {
                $datosActualizacion['comentarios'] = $request->input('comentarios');
            }
            $suscripcion->update($datosActualizacion);

            // Actualizar campos monto_mensual y monto_anual en la empresa
            $empresa = Empresa::findOrFail($suscripcion->empresa_id);
            if ($request->has('monto_mensual')) {
                $empresa->monto_mensual = $request->input('monto_mensual');
            }
            if ($request->has('monto_anual')) {
                $empresa->monto_anual = $request->input('monto_anual');
            }
            // Actualizar frecuencia_pago si viene en el request
            if ($request->has('frecuencia_pago')) {
                $empresa->frecuencia_pago = $request->input('frecuencia_pago');
            }
            $empresa->save();

            // if ($request->input('estado_ultimo_pago') === config('constants.ESTADO_ORDEN_PAGO_COMPLETADO')) {
            //    $this->addOrderPayment($suscripcion);
            // }

            return response()->json([
                'success' => true,
                'data' => $suscripcion
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en SuscripcionesController@edit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la suscripción'
            ], 500);
        }
    }

    /**
     * Registra pago manual (transferencia/efectivo): actualiza fechas de suscripción y opcionalmente orden, venta ERP o factura nueva.
     */
    public function registrarPagoRecibido(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:suscripciones,id',
                'orden_pago_id' => 'nullable|exists:ordenes_pago,id',
                'venta_id' => 'nullable|exists:ventas,id',
                'documento_origen' => 'nullable|in:orden,venta,ninguno',
                'meses_cobertura' => 'nullable|integer|min:1|max:36',
                'crear_venta' => 'nullable|boolean',
            ]);

            if (!empty($validated['orden_pago_id']) && !empty($validated['venta_id'])) {
                throw ValidationException::withMessages([
                    'venta_id' => ['Indica solo una orden de pago o una venta, no ambas.'],
                ]);
            }

            $documentoOrigen = $validated['documento_origen'] ?? null;
            if ($documentoOrigen === null) {
                if (!empty($validated['orden_pago_id'])) {
                    $documentoOrigen = 'orden';
                } elseif (!empty($validated['venta_id'])) {
                    $documentoOrigen = 'venta';
                } else {
                    $documentoOrigen = 'ninguno';
                }
            }

            if ($documentoOrigen === 'orden' && empty($validated['orden_pago_id'])) {
                throw ValidationException::withMessages([
                    'orden_pago_id' => ['Selecciona la orden pendiente que quedó pagada.'],
                ]);
            }
            if ($documentoOrigen === 'venta' && empty($validated['venta_id'])) {
                throw ValidationException::withMessages([
                    'venta_id' => ['Selecciona la venta del ERP que quedó pagada.'],
                ]);
            }
            if ($documentoOrigen === 'venta' && !empty($validated['orden_pago_id'])) {
                throw ValidationException::withMessages([
                    'orden_pago_id' => ['En modo «venta» no debe enviarse orden de pago.'],
                ]);
            }
            if ($documentoOrigen === 'orden' && !empty($validated['venta_id'])) {
                throw ValidationException::withMessages([
                    'venta_id' => ['En modo «orden» no debe enviarse venta_id.'],
                ]);
            }

            $suscripcion = Suscripcion::findOrFail($validated['id']);
            $mesesCobertura = isset($validated['meses_cobertura']) ? (int) $validated['meses_cobertura'] : null;

            if ($mesesCobertura !== null && $mesesCobertura >= 1) {
                $fechaProximoPago = Carbon::now()->addMonths($mesesCobertura);
            } else {
                $dias = max(1, (int) config('constants.DIAS_PAGO_RECIBIDO_PROXIMO_CICLO', 30));
                $fechaProximoPago = Carbon::now()->addDays($dias);
            }

            $crearVenta = $request->boolean('crear_venta');

            DB::transaction(function () use ($suscripcion, $validated, $fechaProximoPago, $documentoOrigen, $crearVenta, $mesesCobertura) {
                if ($documentoOrigen === 'orden' && !empty($validated['orden_pago_id'])) {
                    $orden = $this->ordenPagoPendienteDeSuscripcion($suscripcion, (int) $validated['orden_pago_id']);
                    if (!$orden) {
                        throw ValidationException::withMessages([
                            'orden_pago_id' => ['La orden no está pendiente o no corresponde a esta suscripción.'],
                        ]);
                    }

                    $orden->estado = config('constants.ESTADO_ORDEN_PAGO_COMPLETADO');
                    $orden->fecha_transaccion = Carbon::now();
                    if (!$orden->codigo_autorizacion) {
                        $orden->codigo_autorizacion = 'MANUAL-ADMIN';
                    }
                    if (!$orden->payment_id && $suscripcion->id_pago) {
                        $orden->payment_id = $suscripcion->id_pago;
                    }
                    $orden->save();

                    if ($orden->id_venta) {
                        Venta::where('id', $orden->id_venta)->update([
                            'estado' => 'Pagada',
                            'fecha_pago' => Carbon::now()->format('Y-m-d'),
                            'monto_pago' => $orden->monto,
                        ]);
                    } else {
                        try {
                            $orden->fresh()->generarVenta();
                        } catch (\Throwable $e) {
                            Log::warning('registrarPagoRecibido: no se generó venta ERP para la orden '.$orden->id.': '.$e->getMessage());
                        }
                    }
                } elseif ($documentoOrigen === 'venta' && !empty($validated['venta_id'])) {
                    $venta = Venta::query()->findOrFail((int) $validated['venta_id']);
                    if (!$this->ventaPerteneceSuscripcion($suscripcion, $venta)) {
                        throw ValidationException::withMessages([
                            'venta_id' => ['La venta no pertenece al cliente ERP vinculado a esta empresa.'],
                        ]);
                    }
                    $estadoV = is_string($venta->estado) ? trim($venta->estado) : '';
                    if (strcasecmp($estadoV, 'Pagada') === 0) {
                        throw ValidationException::withMessages([
                            'venta_id' => ['La venta ya está marcada como pagada.'],
                        ]);
                    }
                    $venta->estado = 'Pagada';
                    $venta->fecha_pago = Carbon::now()->format('Y-m-d');
                    $venta->monto_pago = $venta->total;
                    $venta->save();
                }

                if ($crearVenta && $documentoOrigen === 'ninguno' && empty($validated['orden_pago_id']) && empty($validated['venta_id'])) {
                    $mesesParaMonto = ($mesesCobertura !== null && $mesesCobertura >= 1) ? $mesesCobertura : 1;
                    try {
                        $this->crearVentaErpDesdeSuscripcion($suscripcion, $mesesParaMonto);
                    } catch (\Throwable $e) {
                        Log::warning('registrarPagoRecibido: crear_venta falló: '.$e->getMessage());
                        throw ValidationException::withMessages([
                            'crear_venta' => ['No se pudo generar la factura en ERP: '.$e->getMessage()],
                        ]);
                    }
                }

                $suscripcion->fecha_proximo_pago = $fechaProximoPago;
                $suscripcion->fecha_ultimo_pago = Carbon::now();
                $suscripcion->estado_ultimo_pago = config('constants.ESTADO_ORDEN_PAGO_COMPLETADO');
                $suscripcion->acceso_temporal_hasta = null;
                $suscripcion->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado. Próxima fecha de cobro actualizada.',
                'data' => $suscripcion->fresh()->load('plan', 'empresa'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en registrarPagoRecibido: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el pago',
            ], 500);
        }
    }

    /**
     * Busca ventas del ERP (empresa SmartPyME) asociadas al cliente de la empresa suscrita (p. ej. transferencia sin orden N1CO).
     */
    public function buscarVentasSuscripcion(Request $request, int $id)
    {
        $suscripcion = Suscripcion::with('empresa')->findOrFail($id);
        $idCliente = $suscripcion->empresa->id_cliente ?? null;
        if (!$idCliente) {
            return response()->json([], 200);
        }

        $buscar = trim((string) $request->query('buscar', ''));
        $limite = min(40, max(5, (int) $request->query('limite', 25)));

        $q = Venta::query()
            ->where('id_empresa', 2)
            ->where('id_cliente', $idCliente);

        if ($buscar !== '') {
            $q->where(function ($sub) use ($buscar) {
                if (ctype_digit($buscar)) {
                    $sub->where('id', (int) $buscar)
                        ->orWhere('correlativo', 'like', '%'.$buscar.'%');
                } else {
                    $sub->where('correlativo', 'like', '%'.$buscar.'%')
                        ->orWhere('num_cotizacion', 'like', '%'.$buscar.'%');
                }
            });
        } else {
            $q->where(function ($sub) {
                $sub->where('estado', 'Pendiente')
                    ->orWhere('estado', 'En Proceso')
                    ->orWhereRaw('LOWER(TRIM(estado)) = ?', ['pendiente']);
            });
        }

        $filas = $q->with(['documento:id,nombre'])
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limite)
            ->get([
                'id',
                'correlativo',
                'fecha',
                'estado',
                'total',
                'forma_pago',
                'condicion',
                'id_documento',
                'num_cotizacion',
            ]);

        $payload = $filas->map(function (Venta $v) {
            $doc = $v->relationLoaded('documento') ? $v->documento : null;

            return [
                'id' => $v->id,
                'correlativo' => $v->correlativo,
                'fecha' => $v->fecha,
                'estado' => $v->estado,
                'total' => $v->total,
                'forma_pago' => $v->forma_pago,
                'condicion' => $v->condicion,
                'num_cotizacion' => $v->num_cotizacion,
                'documento_nombre' => $doc ? $doc->nombre : null,
            ];
        });

        return response()->json($payload, 200);
    }

    /**
     * Órdenes de pago (N1CO / recurrente) pendientes asociables a esta suscripción.
     */
    public function getOrdenesPagoPendientes($id)
    {
        $suscripcion = Suscripcion::findOrFail($id);

        $ordenes = OrdenPago::query()
            ->where('id_usuario', $suscripcion->usuario_id)
            ->where(function ($q) use ($suscripcion) {
                $q->where('id_plan', $suscripcion->plan_id);
                if ($suscripcion->id_pago) {
                    $q->orWhere('payment_id', $suscripcion->id_pago);
                }
            })
            ->where(function ($q) {
                $pendiente = config('constants.ESTADO_ORDEN_PAGO_PENDIENTE');
                $q->where('estado', $pendiente)
                    ->orWhereRaw('LOWER(TRIM(estado)) = ?', ['pendiente']);
            })
            ->with([
                'venta' => function ($q) {
                    $q->select(
                        'id',
                        'correlativo',
                        'fecha',
                        'estado',
                        'total',
                        'forma_pago',
                        'condicion',
                        'id_documento',
                        'num_cotizacion'
                    )->with(['documento:id,nombre']);
                },
            ])
            ->orderBy('created_at', 'asc')
            ->get([
                'id',
                'id_orden',
                'id_venta',
                'monto',
                'estado',
                'plan',
                'tipo_pago',
                'created_at',
                'fecha_transaccion',
                'nombre_cliente',
                'email_cliente',
            ]);

        $payload = $ordenes->map(function (OrdenPago $orden) {
            $v = $orden->venta;
            $doc = $v && $v->relationLoaded('documento') ? $v->documento : null;

            return [
                'id' => $orden->id,
                'id_orden' => $orden->id_orden,
                'id_venta' => $orden->id_venta,
                'monto' => $orden->monto,
                'estado' => $orden->estado,
                'plan' => $orden->plan,
                'tipo_pago' => $orden->tipo_pago,
                'created_at' => $orden->created_at,
                'fecha_transaccion' => $orden->fecha_transaccion,
                'nombre_cliente' => $orden->nombre_cliente,
                'email_cliente' => $orden->email_cliente,
                'venta' => $v ? [
                    'id' => $v->id,
                    'correlativo' => $v->correlativo,
                    'fecha' => $v->fecha,
                    'estado' => $v->estado,
                    'total' => $v->total,
                    'forma_pago' => $v->forma_pago,
                    'condicion' => $v->condicion,
                    'num_cotizacion' => $v->num_cotizacion,
                    'documento_nombre' => $doc ? $doc->nombre : null,
                ] : null,
            ];
        });

        return response()->json($payload, 200);
    }

    private function ordenPagoPendienteDeSuscripcion(Suscripcion $suscripcion, int $ordenPagoId): ?OrdenPago
    {
        $orden = OrdenPago::query()
            ->where('id', $ordenPagoId)
            ->where('id_usuario', $suscripcion->usuario_id)
            ->where(function ($q) use ($suscripcion) {
                $q->where('id_plan', $suscripcion->plan_id);
                if ($suscripcion->id_pago) {
                    $q->orWhere('payment_id', $suscripcion->id_pago);
                }
            })
            ->first();

        if (!$orden) {
            return null;
        }

        $pendienteCfg = config('constants.ESTADO_ORDEN_PAGO_PENDIENTE');
        $estado = is_string($orden->estado) ? trim($orden->estado) : '';
        $esPendiente = strcasecmp($estado, 'pendiente') === 0 || $estado === $pendienteCfg;

        return $esPendiente ? $orden : null;
    }

    private function ventaPerteneceSuscripcion(Suscripcion $suscripcion, Venta $venta): bool
    {
        if ((int) $venta->id_empresa !== 2) {
            return false;
        }
        $suscripcion->loadMissing('empresa');
        $idCliente = $suscripcion->empresa->id_cliente ?? null;

        return $idCliente && (int) $venta->id_cliente === (int) $idCliente;
    }

    /**
     * Crea factura en ERP (misma lógica que OrdenPago::generarVenta) por cobro manual por meses de plan.
     */
    private function crearVentaErpDesdeSuscripcion(Suscripcion $suscripcion, int $meses): Venta
    {
        $suscripcion->loadMissing(['empresa', 'plan.producto']);
        $documento = Documento::where('id_empresa', 2)->where('nombre', 'Factura')->first();
        $idCliente = $suscripcion->empresa->id_cliente ?? null;
        $producto = $suscripcion->plan ? $suscripcion->plan->producto : null;

        if (!$documento) {
            throw new \RuntimeException('No hay documento Factura configurado en ERP.');
        }
        if (!$idCliente) {
            throw new \RuntimeException('La empresa no está vinculada a un cliente en ERP.');
        }
        if (!$producto) {
            throw new \RuntimeException('El plan no está vinculado a un producto de facturación.');
        }

        $empresa = $suscripcion->empresa;
        $plan = $suscripcion->plan;
        $mensual = (float) ($empresa->monto_mensual ?? $plan->precio ?? $suscripcion->monto ?? 0);
        if ($mensual <= 0) {
            throw new \RuntimeException('No hay monto mensual definido para calcular la factura.');
        }

        $monto = round($mensual * max(1, $meses), 2);
        $descripcion = $producto->nombre.' · Suscripción · '.$meses.' '.($meses === 1 ? 'mes' : 'meses');

        $venta = Venta::create([
            'fecha' => date('Y-m-d'),
            'correlativo' => $documento->correlativo,
            'estado' => 'Pagada',
            'id_canal' => 185,
            'id_documento' => $documento->id,
            'forma_pago' => 'Transferencia',
            'condicion' => 'Contado',
            'fecha_pago' => date('Y-m-d'),
            'fecha_expiracion' => date('Y-m-d'),
            'monto_pago' => $monto,
            'cambio' => 0,
            'iva_percibido' => 0,
            'iva_retenido' => 0,
            'iva' => $monto - ($monto / 1.13),
            'total_costo' => 0,
            'descuento' => 0,
            'sub_total' => $monto / 1.13,
            'gravada' => $monto / 1.13,
            'total' => $monto,
            'id_bodega' => 76,
            'id_cliente' => $idCliente,
            'id_usuario' => 114,
            'id_vendedor' => $suscripcion->usuario_id,
            'id_empresa' => 2,
            'id_sucursal' => 76,
        ]);

        Detalle::create([
            'id_producto' => $producto->id,
            'descripcion' => $descripcion,
            'cantidad' => 1,
            'precio' => $monto / 1.13,
            'costo' => 0,
            'descuento' => 0,
            'gravada' => $monto / 1.13,
            'total_costo' => 0,
            'total' => $monto / 1.13,
            'id_venta' => $venta->id,
        ]);

        Impuesto::create([
            'id_impuesto' => 108,
            'monto' => $venta->iva,
            'id_venta' => $venta->id,
        ]);

        Documento::findOrFail($venta->id_documento)->increment('correlativo');

        return $venta;
    }

    /**
     * Extiende acceso a la plataforma sin mover fecha_proximo_pago (suma N días desde hoy o desde el fin del acceso temporal vigente).
     */
    public function concederAccesoTemporal(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:suscripciones,id',
            ]);
            $suscripcion = Suscripcion::findOrFail($validated['id']);
            $dias = max(1, (int) config('constants.DIAS_ACCESO_TEMPORAL_ADMIN', 2));

            $base = Carbon::now();
            if ($suscripcion->acceso_temporal_hasta) {
                $fin = Carbon::parse($suscripcion->acceso_temporal_hasta);
                if ($fin->greaterThan($base)) {
                    $base = $fin;
                }
            }
            $suscripcion->acceso_temporal_hasta = $base->copy()->addDays($dias);
            $suscripcion->save();

            return response()->json([
                'success' => true,
                'message' => 'Acceso temporal concedido.',
                'data' => $suscripcion->fresh()->load('plan', 'empresa'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en concederAccesoTemporal: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo conceder el acceso temporal',
            ], 500);
        }
    }

    /**
     * Quita el acceso temporal de excepción (vuelve a aplicar solo la regla de mora / paywall).
     */
    public function cancelarAccesoTemporal(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:suscripciones,id',
            ]);
            $suscripcion = Suscripcion::findOrFail($validated['id']);
            $suscripcion->acceso_temporal_hasta = null;
            $suscripcion->save();

            return response()->json([
                'success' => true,
                'message' => 'Acceso temporal cancelado.',
                'data' => $suscripcion->fresh()->load('plan', 'empresa'),
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en cancelarAccesoTemporal: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo cancelar el acceso temporal',
            ], 500);
        }
    }

    public function delete($id)
    {
        $suscripcion = Suscripcion::findOrFail($id);
        $suscripcion->delete();

        return response()->json($suscripcion, 201);
    }

    // Método adicional para empresas sin suscripción
    public function empresasSinSuscripcion(Request $request)
    {
        $empresas = Empresa::whereDoesntHave('suscripcion')
            ->when($request->buscador, function ($query) use ($request) {
                return $query->where('nombre', 'like', '%' . $request->buscador . '%');
            })
            ->orderBy($request->orden ?? 'created_at', $request->direccion ?? 'desc')
            ->paginate($request->paginate ?? 10);

        return response()->json($empresas, 200);
    }

    public function cancelSuscription(Request $request)
    {
        $suscripcion = Suscripcion::findOrFail($request->id);
        $suscripcion->estado = config('constants.ESTADO_SUSCRIPCION_CANCELADO');
        $suscripcion->motivo_cancelacion = $request->motivo_cancelacion;
        $suscripcion->fecha_cancelacion = now();
        $suscripcion->save();

        $empresa = Empresa::findOrFail($suscripcion->usuario->empresa->id);
        $empresa->fecha_cancelacion = now();
        $empresa->save();

        return response()->json($suscripcion, 200);
    }

    public function suspendSystem(Request $request)
    {
        try {
            $suscripcion = null;
            if ($request->input('empresa.suscripcion.id')) {
                $suscripcion = Suscripcion::find($request->input('empresa.suscripcion.id'));
            }

            if (!$suscripcion) {
                $user = User::where('id_empresa', $request->input('empresa.id'))->first();
                if ($user) {
                    $user->enable = false;
                    $user->save();

                    $plan = Plan::where('nombre', $request->input('empresa.plan'))->first();
                    $suscripcion = new Suscripcion();
                    $suscripcion->empresa_id = $request->input('empresa.id');
                    $suscripcion->usuario_id = $user->id;

                    $suscripcion->plan_id = $plan->id;
                    $suscripcion->tipo_plan = $plan->getTipoPlanAttribute();
                    $suscripcion->monto = $plan->precio;

                    $suscripcion->estado = config('constants.ESTADO_SUSCRIPCION_SUSPENDIDO');
                    $suscripcion->save();

                    // Desactivar la empresa
                    $empresa = Empresa::find($request->input('empresa.id'));
                    if ($empresa) {
                        $empresa->activo = false;
                        $empresa->save();
                    }
                }
            } else {
                $suscripcion->estado = config('constants.ESTADO_SUSCRIPCION_SUSPENDIDO');
                $suscripcion->save();

                $user = User::find($suscripcion->usuario_id);
                if ($user) {
                    $user->enable = false;
                    $user->save();
                }

                // Desactivar la empresa
                $empresa = Empresa::find($suscripcion->empresa_id);
                if ($empresa) {
                    $empresa->activo = false;
                    $empresa->save();
                }
            }

            return response()->json($suscripcion ?? ['message' => 'Sistema suspendido'], 200);
        } catch (\Throwable $th) {
            Log::error('Error en SuscripcionesController@suspendSystem: ' . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al suspender el sistema'
            ], 500);
        }
    }

    public function getHistorialPagos($id)
    {
        $suscripcion = Suscripcion::findOrFail($id);

        return $suscripcion->ordenesPago()
            ->select([
                'id',
                'id_orden',
                'monto',
                'estado',
                'codigo_autorizacion',
                'fecha_transaccion',
                // 'metodo_pago'
            ])
            ->orderBy('fecha_transaccion', 'desc')
            ->get();
    }

    public function getUsersSelect(Request $request)
    {
        return User::select('id', 'name')
            ->where('enable', true)
            // ->where('id_sucursal', $request->id_sucursal)
            ->where('id_empresa', $request->input('params.id_empresa'))
            ->where('tipo', config('constants.TIPO_USUARIO_ADMINISTRADOR'))
            ->orderBy('name', 'asc')
            ->get();
    }

    private function addOrderPayment(Suscripcion $suscripcion)
    {
        try {
            $ordenPago = new OrdenPago();
            $ordenPago->id_usuario = $suscripcion->usuario_id;
            $ordenPago->id_plan = $suscripcion->plan_id;
            $ordenPago->monto = $suscripcion->monto;
            $ordenPago->estado = config('constants.ESTADO_ORDEN_PAGO_COMPLETADO');
            $ordenPago->save();

            return $ordenPago;
        } catch (\Throwable $th) {
            Log::error('Error en SuscripcionesController@addOrderPayment: ' . $th->getMessage());
            return null;
        }
    }

    // public function export(Request $request)
    // {
    //     $suscripciones = new SuscripcionesExport();
    //     $suscripciones->filter($request);

    //     return Excel::download($suscripciones, 'suscripciones_'.date('Y-m-d').'.xlsx');
    // }

    public function export(Request $request)
    {
        try {
            $export = new SuscripcionesExport();
            $export->filter($request);

            $filename = 'suscripciones_empresas_' . date('Y-m-d_H-i-s') . '.xlsx';

            return Excel::download($export, $filename);
        } catch (\Exception $e) {
            Log::error('Error en export de suscripciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error al exportar datos'], 500);
        }
    }

    public function getCampanias(Request $request)
    {
        try {
            $searchTerm = $request->input('search', '');

            $query = Empresa::select('campania')
                ->whereNotNull('campania')
                ->where('campania', '!=', '');

            if ($searchTerm) {
                $query->where('campania', 'like', '%' . $searchTerm . '%');
            }

            $campanias = $query->distinct()
                ->orderBy('campania', 'asc')
                ->limit(50)
                ->pluck('campania')
                ->filter()
                ->values();

            return response()->json($campanias, 200);
        } catch (\Exception $e) {
            Log::error('Error en SuscripcionesController@getCampanias: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener campañas'], 500);
        }
    }
}
