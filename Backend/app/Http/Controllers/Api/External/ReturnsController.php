<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Devoluciones\Devolucion;
use Illuminate\Http\Request;
use App\Http\Resources\External\ReturnResource;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\External\Returns\IndexReturnsRequest;
use App\Http\Requests\External\Returns\SummaryReturnsRequest;

/**
 * @OA\Tag(
 *     name="Returns",
 *     description="Endpoints para consultar información de devoluciones de ventas"
 * )
 */
class ReturnsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/returns",
     *     tags={"Returns"},
     *     summary="Listar devoluciones de ventas",
     *     description="Obtiene una lista paginada de devoluciones con filtros opcionales",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         description="Fecha de inicio (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         description="Fecha de fin (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="id_venta",
     *         in="query",
     *         description="ID de la venta original",
     *         required=false,
     *         @OA\Schema(type="integer", example="123")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=200, default=100, example=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de devoluciones obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Return")),
     *             @OA\Property(property="pagination", ref="#/components/schemas/Pagination")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimitExceeded"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerError")
     * )
     */
    public function index(IndexReturnsRequest $request)
    {
        try {
            $empresa = $request->attributes->get('empresa');

            // Construir consulta
            $query = Devolucion::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->with('detalles');

            // Aplicar filtros
            if ($request->filled('fecha_inicio')) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->filled('fecha_fin')) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            if ($request->filled('id_venta')) {
                $query->where('id_venta', $request->id_venta);
            }

            // Paginación
            $perPage = min($request->get('per_page', 100), 200);
            $devoluciones = $query->orderBy('fecha', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            // Log de acceso exitoso
            Log::info('Consulta API externa de devoluciones exitosa', [
                'empresa_id' => $empresa->id,
                'filtros' => $request->only(['fecha_inicio', 'fecha_fin', 'id_venta']),
                'total_resultados' => $devoluciones->total(),
                'pagina' => $devoluciones->currentPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => ReturnResource::collection($devoluciones->items()),
                'pagination' => [
                    'current_page' => $devoluciones->currentPage(),
                    'per_page' => $devoluciones->perPage(),
                    'total' => $devoluciones->total(),
                    'total_pages' => $devoluciones->lastPage(),
                    'has_next' => $devoluciones->hasMorePages(),
                    'has_prev' => $devoluciones->currentPage() > 1,
                    'from' => $devoluciones->firstItem(),
                    'to' => $devoluciones->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de devoluciones', [
                'empresa_id' => $empresa->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/returns/{id}",
     *     tags={"Returns"},
     *     summary="Obtener devolución específica",
     *     description="Obtiene los detalles completos de una devolución específica",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la devolución",
     *         required=true,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Devolución obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Return")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimitExceeded"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerError")
     * )
     */
    public function show($devolucionId, Request $request)
    {
        try {
            $empresa = $request->attributes->get('empresa');
            
            // Validar que el ID sea numérico
            if (!is_numeric($devolucionId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ID de devolución inválido',
                    'code' => 400
                ], 400);
            }

            // Buscar la devolución
            $devolucion = Devolucion::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->where('id', $devolucionId)
                ->with('detalles')
                ->first();

            if (!$devolucion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Devolución no encontrada',
                    'code' => 404
                ], 404);
            }

            // Log de acceso exitoso
            Log::info('Consulta API externa de devolución específica exitosa', [
                'empresa_id' => $empresa->id,
                'devolucion_id' => $devolucionId,
                'venta_original_id' => $devolucion->id_venta
            ]);

            return response()->json([
                'success' => true,
                'data' => new ReturnResource($devolucion)
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de devolución específica', [
                'empresa_id' => $empresa->id ?? null,
                'devolucion_id' => $devolucionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/returns/summary",
     *     tags={"Returns"},
     *     summary="Obtener resumen de devoluciones",
     *     description="Obtiene un resumen estadístico de las devoluciones",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         description="Fecha de inicio (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         description="Fecha de fin (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resumen de devoluciones obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_devoluciones", type="integer", example=25),
     *                 @OA\Property(property="total_monto", type="number", format="float", example=5250.75),
     *                 @OA\Property(property="promedio_devolucion", type="number", format="float", example=210.03),
     *                 @OA\Property(property="periodo", type="object",
     *                     @OA\Property(property="fecha_inicio", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="fecha_fin", type="string", format="date", example="2024-12-31")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthorized"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimitExceeded"),
     *     @OA\Response(response=500, ref="#/components/responses/ServerError")
     * )
     */
    public function summary(SummaryReturnsRequest $request)
    {
        try {
            $empresa = $request->attributes->get('empresa');

            // Construir consulta
            $query = Devolucion::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id);

            // Aplicar filtros de fecha
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;

            if ($fechaInicio) {
                $query->where('fecha', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->where('fecha', '<=', $fechaFin);
            }

            // Obtener estadísticas
            $totalDevoluciones = $query->count();
            $totalMonto = $query->sum('total');
            $promedio = $totalDevoluciones > 0 ? $totalMonto / $totalDevoluciones : 0;

            // Log de acceso exitoso
            Log::info('Consulta API externa de resumen de devoluciones exitosa', [
                'empresa_id' => $empresa->id,
                'filtros' => $request->only(['fecha_inicio', 'fecha_fin']),
                'total_devoluciones' => $totalDevoluciones
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_devoluciones' => $totalDevoluciones,
                    'total_monto' => round($totalMonto, 2),
                    'promedio_devolucion' => round($promedio, 2),
                    'periodo' => [
                        'fecha_inicio' => $fechaInicio,
                        'fecha_fin' => $fechaFin
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de resumen de devoluciones', [
                'empresa_id' => $empresa->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }
}
