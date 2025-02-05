<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JWTAuth;


class SuscripcionesController extends Controller
{
    
    public function index(Request $request)
    {
        try {
            $query = Empresa::with(['suscripcion', 'suscripcion.plan' => function ($query) {
                $query->select('id', 'nombre', 'precio', 'slug', 'duracion_dias');
            }])
                ->when($request->estado, function ($q) use ($request) {
                    if ($request->estado === 'sin_suscripcion') {
                        return $q->whereDoesntHave('suscripcion');
                    } else {
                        return $q->whereHas('suscripcion', function ($query) use ($request) {
                            $query->where('estado', $request->estado);
                        });
                    }
                })
                ->when($request->buscador, function ($q) use ($request) {
                    return $q->where('nombre', 'like', '%' . $request->buscador . '%');
                });

            // Si no hay filtro de estado, incluimos todas las empresas
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

            // Debug para verificar la estructura de datos
            Log::info('Empresas con suscripciones:', [
                'primera_empresa' => $empresas->first(),
                'total' => $empresas->total()
            ]);

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

    public function store(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|numeric',
            'plan_id' => 'required|numeric',
            'estado' => 'required|in:activo,cancelado,vencido,prueba',
            'monto' => 'required|numeric'
        ]);

        if ($request->id) {
            $suscripcion = Suscripcion::findOrFail($request->id);
        } else {
            $suscripcion = new Suscripcion;
        }

        $suscripcion->fill($request->all());
        $suscripcion->save();

        return response()->json($suscripcion, 200);
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
}
