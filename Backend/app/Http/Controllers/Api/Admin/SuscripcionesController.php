<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\SuscripcionesExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\OrdenPago;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Http\Request;
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

            $datosActualizacion = [
                'fecha_proximo_pago' => $validated['fecha_proximo_pago'],
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
