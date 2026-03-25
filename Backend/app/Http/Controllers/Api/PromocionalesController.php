<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promocional;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromocionalesController extends Controller
{
    /**
     * Valida un código promocional y retorna su información si es válido
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validar(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string',
            'tipo_plan' => 'nullable|string' // Para validar planes permitidos
        ]);

        $codigo = strtoupper(trim($request->codigo));
        $tipoPlan = $request->tipo_plan ? strtolower($request->tipo_plan) : null;

        // Buscar el código promocional en la base de datos
        $promocional = Promocional::where('codigo', $codigo)
            ->where('activo', true)
            ->first();

        if (!$promocional) {
            return response()->json([
                'valido' => false,
                'mensaje' => 'Código promocional no encontrado o inactivo'
            ], 404);
        }

        // Validar fechas de expiración si están definidas
        $opciones = $promocional->opciones ?? [];
        if (isset($opciones['fecha_expiracion'])) {
            $fechaExpiracion = Carbon::parse($opciones['fecha_expiracion']);
            if (now()->gt($fechaExpiracion)) {
                return response()->json([
                    'valido' => false,
                    'mensaje' => 'Código promocional expirado'
                ], 400);
            }
        }

        if (isset($opciones['fecha_inicio'])) {
            $fechaInicio = Carbon::parse($opciones['fecha_inicio']);
            if (now()->lt($fechaInicio)) {
                return response()->json([
                    'valido' => false,
                    'mensaje' => 'Código promocional aún no está disponible'
                ], 400);
            }
        }

        // Validar planes permitidos si están definidos
        if ($tipoPlan && !empty($promocional->planes_permitidos)) {
            $planesPermitidos = array_map('strtolower', $promocional->planes_permitidos);
            if (!in_array($tipoPlan, $planesPermitidos)) {
                return response()->json([
                    'valido' => false,
                    'mensaje' => 'Este código promocional no es válido para el plan seleccionado'
                ], 400);
            }
        }

        // Calcular descuento según el tipo
        $descuentoPorcentaje = 0;
        if ($promocional->tipo === 'porcentaje') {
            $descuentoPorcentaje = $promocional->descuento; // Ya está en porcentaje (ej: 50.00 = 50%)
        }

        return response()->json([
            'valido' => true,
            'codigo' => $promocional->codigo,
            'descuento' => $descuentoPorcentaje,
            'tipo' => $promocional->tipo,
            'campania' => $promocional->campania,
            'descripcion' => $promocional->descripcion,
            'planes_permitidos' => $promocional->planes_permitidos,
            'opciones' => $opciones
        ], 200);
    }

    /**
     * Obtiene la lista de códigos promocionales activos
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $promocionales = Promocional::where('activo', true)
            ->orderBy('codigo', 'asc')
            ->get()
            ->map(function ($promocional) {
                // Filtrar códigos expirados o que aún no están disponibles
                $opciones = $promocional->opciones ?? [];
                
                if (isset($opciones['fecha_expiracion'])) {
                    $fechaExpiracion = Carbon::parse($opciones['fecha_expiracion']);
                    if (now()->gt($fechaExpiracion)) {
                        return null; // Código expirado
                    }
                }

                if (isset($opciones['fecha_inicio'])) {
                    $fechaInicio = Carbon::parse($opciones['fecha_inicio']);
                    if (now()->lt($fechaInicio)) {
                        return null; // Código aún no válido
                    }
                }

                return [
                    'id' => $promocional->id,
                    'codigo' => $promocional->codigo,
                    'descuento' => $promocional->descuento,
                    'tipo' => $promocional->tipo,
                    'campania' => $promocional->campania,
                    'descripcion' => $promocional->descripcion,
                    'planes_permitidos' => $promocional->planes_permitidos,
                ];
            })
            ->filter() // Eliminar nulls (códigos expirados o no disponibles)
            ->values();

        return response()->json($promocionales, 200);
    }

    /**
     * Obtiene todos los códigos promocionales (incluyendo inactivos) para administración
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $query = Promocional::query();

        // Filtros
        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', $request->activo === 'true' || $request->activo === '1');
        }

        if ($request->has('buscador') && !empty($request->buscador)) {
            $buscador = $request->buscador;
            $query->where(function($q) use ($buscador) {
                $q->where('codigo', 'like', "%{$buscador}%")
                  ->orWhere('campania', 'like', "%{$buscador}%")
                  ->orWhere('descripcion', 'like', "%{$buscador}%");
            });
        }

        // Ordenamiento
        $orden = $request->get('orden', 'codigo');
        $direccion = $request->get('direccion', 'asc');
        $query->orderBy($orden, $direccion);

        // Paginación
        $paginate = $request->get('paginate', 25);
        $promocionales = $query->paginate($paginate);

        return response()->json($promocionales, 200);
    }

    /**
     * Obtiene un código promocional por ID
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function read($id): JsonResponse
    {
        $promocional = Promocional::find($id);

        if (!$promocional) {
            return response()->json([
                'message' => 'Código promocional no encontrado'
            ], 404);
        }

        return response()->json($promocional, 200);
    }

    /**
     * Crea un nuevo código promocional
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string|unique:promocionales,codigo',
            'descuento' => 'required|numeric|min:0',
            'tipo' => 'required|in:porcentaje,monto_fijo',
            'activo' => 'boolean',
            'campania' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'planes_permitidos' => 'nullable|array',
            'opciones' => 'nullable|array',
        ]);

        $promocional = Promocional::create([
            'codigo' => strtoupper(trim($request->codigo)),
            'descuento' => $request->descuento,
            'tipo' => $request->tipo,
            'activo' => $request->has('activo') ? $request->activo : true,
            'campania' => $request->campania,
            'descripcion' => $request->descripcion,
            'planes_permitidos' => $request->planes_permitidos ?? [],
            'opciones' => $request->opciones ?? [],
        ]);

        return response()->json($promocional, 201);
    }

    /**
     * Actualiza un código promocional
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:promocionales,id',
        ]);

        $id = $request->id;
        $promocional = Promocional::find($id);

        if (!$promocional) {
            return response()->json([
                'message' => 'Código promocional no encontrado'
            ], 404);
        }

        $request->validate([
            'codigo' => 'sometimes|required|string|unique:promocionales,codigo,' . $id,
            'descuento' => 'sometimes|required|numeric|min:0',
            'tipo' => 'sometimes|required|in:porcentaje,monto_fijo',
            'activo' => 'boolean',
            'campania' => 'nullable|string',
            'descripcion' => 'nullable|string',
            'planes_permitidos' => 'nullable|array',
            'opciones' => 'nullable|array',
        ]);

        $promocional->update([
            'codigo' => $request->has('codigo') ? strtoupper(trim($request->codigo)) : $promocional->codigo,
            'descuento' => $request->has('descuento') ? $request->descuento : $promocional->descuento,
            'tipo' => $request->has('tipo') ? $request->tipo : $promocional->tipo,
            'activo' => $request->has('activo') ? $request->activo : $promocional->activo,
            'campania' => $request->has('campania') ? $request->campania : $promocional->campania,
            'descripcion' => $request->has('descripcion') ? $request->descripcion : $promocional->descripcion,
            'planes_permitidos' => $request->has('planes_permitidos') ? $request->planes_permitidos : $promocional->planes_permitidos,
            'opciones' => $request->has('opciones') ? $request->opciones : $promocional->opciones,
        ]);

        return response()->json($promocional, 200);
    }

    /**
     * Elimina un código promocional
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function delete($id): JsonResponse
    {
        $promocional = Promocional::find($id);

        if (!$promocional) {
            return response()->json([
                'message' => 'Código promocional no encontrado'
            ], 404);
        }

        $promocional->delete();

        return response()->json([
            'message' => 'Código promocional eliminado correctamente'
        ], 200);
    }
}

