<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;
use App\Http\Resources\External\SaleResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Info(
 *     title="SmartPYME External API",
 *     version="1.0.0",
 *     description="API Externa para proveedores terceros - Acceso a datos de ventas e inventario",
 *     @OA\Contact(
 *         email="soporte@smartpyme.com",
 *         name="SmartPYME Support"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://tu-dominio.com/api/external/v1",
 *     description="Servidor de Producción"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="ApiKeyAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="API Key de la empresa en formato Bearer token"
 * )
 * 
 * @OA\Tag(
 *     name="Ventas",
 *     description="Endpoints para consultar información de ventas"
 * )
 */

class SalesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/sales",
     *     tags={"Ventas"},
     *     summary="Listar ventas",
     *     description="Obtiene una lista paginada de ventas con filtros opcionales",
     *     security={{"ApiKeyAuth": {}}},
     *     @OA\Parameter(
     *         name="fecha_inicio",
     *         in="query",
     *         description="Fecha de inicio (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_fin",
     *         in="query",
     *         description="Fecha de fin (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="estado",
     *         in="query",
     *         description="Estado de la venta",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Completada", "Pendiente", "Anulada", "Cotizacion"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Registros por página",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=200, default=100)
     *     ),
     *     @OA\Parameter(
     *         name="order_by",
     *         in="query",
     *         description="Campo de ordenamiento",
     *         required=false,
     *         @OA\Schema(type="string", enum={"fecha", "total", "correlativo", "created_at"}, default="fecha")
     *     ),
     *     @OA\Parameter(
     *         name="order_direction",
     *         in="query",
     *         description="Dirección del ordenamiento",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de ventas obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="fecha", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="correlativo", type="string", example="FAC-001"),
     *                     @OA\Property(property="estado", type="string", example="Completada"),
     *                     @OA\Property(property="total", type="number", format="float", example=150.75),
     *                     @OA\Property(property="nombre_cliente", type="string", example="Juan Pérez")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=100),
     *                 @OA\Property(property="total", type="integer", example=1250)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=429, description="Rate limit excedido")
     * )
     *
     * Obtener lista de ventas
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'fecha_fin' => 'nullable|date|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'estado' => 'nullable|string|in:Completada,Pendiente,Anulada,Cotizacion',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:200',
                'order_by' => 'nullable|string|in:fecha,total,correlativo,created_at',
                'order_direction' => 'nullable|string|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            // Obtener empresa desde el middleware
            $empresa = $request->attributes->get('empresa');
            
            // Configurar paginación
            $perPage = $request->get('per_page', 100);
            $page = $request->get('page', 1);
            
            // Construir query
            $query = Venta::withoutGlobalScopes()
                          ->where('id_empresa', $empresa->id)
                          ->withAccessorRelations()
                          ->with(['detalles']);

            // Aplicar filtros
            if ($request->filled('fecha_inicio')) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->filled('fecha_fin')) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Aplicar ordenamiento
            $orderBy = $request->get('order_by', 'fecha');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Ejecutar consulta paginada
            $ventas = $query->paginate($perPage, ['*'], 'page', $page);

            // Limpiar appends problemáticos antes de crear el Resource
            $ventas->getCollection()->transform(function ($venta) {
                $venta->makeHidden(['nombre_proyecto']);
                return $venta;
            });

            // Log de consulta exitosa
            Log::info('Consulta API externa de ventas exitosa', [
                'empresa_id' => $empresa->id,
                'filtros' => $request->only(['fecha_inicio', 'fecha_fin', 'estado']),
                'resultados' => $ventas->count(),
                'pagina' => $page,
                'por_pagina' => $perPage
            ]);

            return response()->json([
                'success' => true,
                'data' => SaleResource::collection($ventas->items()),
                'pagination' => [
                    'current_page' => $ventas->currentPage(),
                    'per_page' => $ventas->perPage(),
                    'total' => $ventas->total(),
                    'total_pages' => $ventas->lastPage(),
                    'has_next' => $ventas->hasMorePages(),
                    'has_prev' => $ventas->currentPage() > 1,
                    'from' => $ventas->firstItem(),
                    'to' => $ventas->lastItem()
                ],
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString(),
                    'filters_applied' => $request->only(['fecha_inicio', 'fecha_fin', 'estado'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de ventas', [
                'empresa_id' => $request->attributes->get('empresa')->id ?? null,
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
     * Obtener una venta específica
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $ventaId
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $ventaId)
    {
        try {
            // Obtener empresa desde el middleware
            $empresa = $request->attributes->get('empresa');

            // Buscar la venta
            $venta = Venta::withoutGlobalScopes()
                          ->where('id_empresa', $empresa->id)
                          ->where('id', $ventaId)
                          ->with(['detalles'])
                          ->first();

            if (!$venta) {
                return response()->json([
                    'success' => false,
                    'error' => 'Venta no encontrada',
                    'code' => 404
                ], 404);
            }

            // Limpiar appends problemáticos
            $venta->makeHidden(['nombre_proyecto']);

            // Log de consulta exitosa
            Log::info('Consulta API externa de venta individual exitosa', [
                'empresa_id' => $empresa->id,
                'venta_id' => $ventaId
            ]);

            return response()->json([
                'success' => true,
                'data' => new SaleResource($venta),
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de venta individual', [
                'empresa_id' => $request->attributes->get('empresa')->id ?? null,
                'venta_id' => $ventaId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }

    /**
     * Obtener resumen de ventas
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summary(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'nullable|date|date_format:Y-m-d',
                'fecha_fin' => 'nullable|date|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'estado' => 'nullable|string|in:Completada,Pendiente,Anulada,Cotizacion',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parámetros inválidos',
                    'details' => $validator->errors()
                ], 400);
            }

            // Obtener empresa desde el middleware
            $empresa = $request->attributes->get('empresa');
            
            // Construir query base
            $query = Venta::withoutGlobalScopes()->where('id_empresa', $empresa->id);

            // Aplicar filtros
            if ($request->filled('fecha_inicio')) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->filled('fecha_fin')) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            // Calcular resumen
            $summary = [
                'cantidad_ventas' => $query->count(),
                'total_ventas' => round($query->sum('total'), 2),
                'total_iva' => round($query->sum('iva'), 2),
                'total_descuentos' => round($query->sum('descuento'), 2),
                'promedio_venta' => 0,
                'ventas_por_estado' => []
            ];

            // Calcular promedio
            if ($summary['cantidad_ventas'] > 0) {
                $summary['promedio_venta'] = round($summary['total_ventas'] / $summary['cantidad_ventas'], 2);
            }

            // Ventas por estado
            $ventasPorEstado = Venta::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->when($request->filled('fecha_inicio'), function ($q) use ($request) {
                    return $q->where('fecha', '>=', $request->fecha_inicio);
                })
                ->when($request->filled('fecha_fin'), function ($q) use ($request) {
                    return $q->where('fecha', '<=', $request->fecha_fin);
                })
                ->selectRaw('estado, COUNT(*) as cantidad, SUM(total) as total')
                ->groupBy('estado')
                ->get();

            foreach ($ventasPorEstado as $estado) {
                $summary['ventas_por_estado'][] = [
                    'estado' => $estado->estado,
                    'cantidad' => $estado->cantidad,
                    'total' => round($estado->total, 2)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $summary,
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString(),
                    'filters_applied' => $request->only(['fecha_inicio', 'fecha_fin', 'estado'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en resumen API externa de ventas', [
                'empresa_id' => $request->attributes->get('empresa')->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'code' => 500
            ], 500);
        }
    }
}
