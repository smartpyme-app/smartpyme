<?php

namespace App\Http\Controllers;

use App\Services\ClienteNotasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ClienteNotasController extends Controller
{
    protected $clienteNotasService;

    public function __construct(ClienteNotasService $clienteNotasService)
    {
        $this->clienteNotasService = $clienteNotasService;
    }

    /**
     * Obtener notas de un cliente
     */
    public function getNotas(Request $request, int $clienteId): JsonResponse
    {
        try {
            $filtros = $request->only([
                'tipo', 'prioridad', 'responsable', 'fecha_desde', 'fecha_hasta', 'resuelto'
            ]);

            $notas = $this->clienteNotasService->getNotasCliente($clienteId, $filtros);

            return response()->json([
                'success' => true,
                'data' => $notas,
                'message' => 'Notas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las notas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener visitas de un cliente
     */
    public function getVisitas(Request $request, int $clienteId): JsonResponse
    {
        try {
            $filtros = $request->only([
                'tipo_visita', 'estado', 'responsable', 'fecha_desde', 'fecha_hasta'
            ]);

            $visitas = $this->clienteNotasService->getVisitasCliente($clienteId, $filtros);

            return response()->json([
                'success' => true,
                'data' => $visitas,
                'message' => 'Visitas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las visitas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva nota
     */
    public function crearNota(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required|exists:clientes,id',
                'tipo' => 'required|in:preferencias,quejas,comentarios,visita,llamada,whatsapp,email',
                'titulo' => 'required|string|max:255',
                'contenido' => 'required|string',
                'responsable' => 'nullable|string|max:255',
                'prioridad' => 'nullable|in:low,medium,high',
                'fecha_interaccion' => 'required|date',
                'hora_interaccion' => 'required|date_format:H:i',
                'fecha_seguimiento' => 'nullable|date|after_or_equal:fecha_interaccion',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nota = $this->clienteNotasService->crearNota($request->all());

            return response()->json([
                'success' => true,
                'data' => $nota,
                'message' => 'Nota creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva visita
     */
    public function crearVisita(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente_id' => 'required|exists:clientes,id',
                'tipo_visita' => 'required|in:presencial,virtual,llamada,whatsapp,email',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'responsable' => 'nullable|string|max:255',
                'prioridad' => 'nullable|in:low,medium,high',
                'fecha_visita' => 'required|date',
                'hora_visita' => 'required|date_format:H:i',
                'duracion_minutos' => 'nullable|integer|min:1',
                'valor_potencial' => 'nullable|numeric|min:0',
                'estado' => 'nullable|in:programada,realizada,cancelada',
                'resultados' => 'nullable|string',
                'proximos_pasos' => 'nullable|string',
                'fecha_seguimiento' => 'nullable|date|after_or_equal:fecha_visita',
                'requiere_seguimiento' => 'nullable|boolean',
                'productos_mencionados' => 'nullable|array',
                'servicios_mencionados' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $visita = $this->clienteNotasService->crearVisita($request->all());

            return response()->json([
                'success' => true,
                'data' => $visita,
                'message' => 'Visita creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la visita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una nota
     */
    public function actualizarNota(Request $request, int $notaId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo' => 'nullable|in:preferencias,quejas,comentarios,visita,llamada,whatsapp,email',
                'titulo' => 'nullable|string|max:255',
                'contenido' => 'nullable|string',
                'prioridad' => 'nullable|in:low,medium,high',
                'fecha_interaccion' => 'nullable|date',
                'hora_interaccion' => 'nullable|date_format:H:i',
                'fecha_seguimiento' => 'nullable|date',
                'resuelto' => 'nullable|boolean',
                'resolucion' => 'nullable|string',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nota = $this->clienteNotasService->actualizarNota($notaId, $request->all());

            return response()->json([
                'success' => true,
                'data' => $nota,
                'message' => 'Nota actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una visita
     */
    public function actualizarVisita(Request $request, int $visitaId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_visita' => 'nullable|in:presencial,virtual,llamada,whatsapp,email',
                'titulo' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'prioridad' => 'nullable|in:low,medium,high',
                'fecha_visita' => 'nullable|date',
                'hora_visita' => 'nullable|date_format:H:i',
                'duracion_minutos' => 'nullable|integer|min:1',
                'valor_potencial' => 'nullable|numeric|min:0',
                'estado' => 'nullable|in:programada,realizada,cancelada',
                'resultados' => 'nullable|string',
                'proximos_pasos' => 'nullable|string',
                'fecha_seguimiento' => 'nullable|date',
                'requiere_seguimiento' => 'nullable|boolean',
                'productos_mencionados' => 'nullable|array',
                'servicios_mencionados' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $visita = $this->clienteNotasService->actualizarVisita($visitaId, $request->all());

            return response()->json([
                'success' => true,
                'data' => $visita,
                'message' => 'Visita actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la visita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una nota
     */
    public function eliminarNota(int $notaId): JsonResponse
    {
        try {
            $this->clienteNotasService->eliminarNota($notaId);

            return response()->json([
                'success' => true,
                'message' => 'Nota eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una visita
     */
    public function eliminarVisita(int $visitaId): JsonResponse
    {
        try {
            $this->clienteNotasService->eliminarVisita($visitaId);

            return response()->json([
                'success' => true,
                'message' => 'Visita eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la visita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de notas y visitas
     */
    public function getEstadisticas(int $clienteId): JsonResponse
    {
        try {
            $estadisticas = $this->clienteNotasService->getEstadisticas($clienteId);

            return response()->json([
                'success' => true,
                'data' => $estadisticas,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar notas por contenido
     */
    public function buscarNotas(Request $request, int $clienteId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'termino' => 'required|string|min:2|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Término de búsqueda inválido',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notas = $this->clienteNotasService->buscarNotas($clienteId, $request->termino);

            return response()->json([
                'success' => true,
                'data' => $notas,
                'message' => 'Búsqueda completada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
}
