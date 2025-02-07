<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JWTAuth;


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
            ->when($request->plan, function ($q) use ($request) {
                return $q->whereHas('suscripcion.plan', function ($query) use ($request) {
                    $query->where('nombre', $request->plan);
                });
            })
            ->when($request->forma_pago, function ($q) use ($request) {
                return $q->where('metodo_pago', $request->forma_pago);
            });

            if (!$request->estado) {
                $query->withCount('suscripcion');
            }

            $empresas = $query->orderBy($request->orden ?? 'created_at', $request->direccion ?? 'desc')
                ->paginate($request->paginate ?? 10);

            // Obtener el tipo de plan para cada empresa
            $empresas->getCollection()->transform(function ($empresa) {
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

    public function createSuscription(Request $request)
    {
        try {
            $validated = $request->validate([
                'empresa_id' => 'required|exists:empresas,id',
                'plan_id' => 'required|exists:planes,id',
                'usuario_id' => 'required|exists:users,id',
                'tipo_plan' => 'required|in:Mensual,Anual',
                'estado' => 'required|in:Activo,Cancelado,Vencido,En prueba,Pendiente',
                'monto' => 'required|numeric|min:0',
                'fecha_proximo_pago' => 'required|date',
                'fin_periodo_prueba' => 'required|date',
                // Campos opcionales
                'nit' => 'nullable|string|max:20',
                'nombre_factura' => 'nullable|string|max:255',
                'direccion_factura' => 'nullable|string|max:500',
                'requiere_factura' => 'boolean',
                'motivo_cancelacion' => 'nullable|string|max:500'
            ]);

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
                if (empty($request->motivo_cancelacion)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El motivo de cancelación es requerido'
                    ], 422);
                }
                $suscripcion->motivo_cancelacion = $request->motivo_cancelacion;
                $suscripcion->fecha_cancelacion = now();
            }

            $suscripcion->intentos_cobro = 0;
            $suscripcion->estado_ultimo_pago = 'Pendiente';

            $suscripcion->save();

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

    public function editSuscription(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:suscripciones,id',
                'fecha_proximo_pago' => 'required|date',
                'fin_periodo_prueba' => 'required|date',
                'estado' => 'required|string',
                'monto' => 'required|numeric',
                'plan_id' => 'required|exists:planes,id',
                'tipo_plan' => 'required|string',
                'nombre_factura' => 'nullable|string',
                'direccion_factura' => 'nullable|string',
                'motivo_cancelacion' => 'nullable|string',
                'nit' => 'nullable|string'
            ]);

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

            $suscripcion->update([
                'fecha_proximo_pago' => $validated['fecha_proximo_pago'],
                'estado' => $validated['estado'],
                'fin_periodo_prueba' => $validated['fin_periodo_prueba'],
                'nit' => $request->input('nit'),
                'nombre_factura' => $request->input('nombre_factura'),
                'direccion_factura' => $request->input('direccion_factura'),
                'motivo_cancelacion' => $request->input('motivo_cancelacion'),
            ]);

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
                }
            } else {
                $suscripcion->estado = config('constants.ESTADO_SUSCRIPCION_SUSPENDIDO');
                $suscripcion->save();

                $user = User::find($suscripcion->usuario_id);
                if ($user) {
                    $user->enable = false;
                    $user->save();
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
}
