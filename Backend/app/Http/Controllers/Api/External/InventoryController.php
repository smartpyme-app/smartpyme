<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use Illuminate\Http\Request;
use App\Http\Resources\External\InventoryResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Obtener lista de productos con inventario
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'codigo' => 'nullable|string',
                'nombre' => 'nullable|string',
                'categoria' => 'nullable|string',
                'marca' => 'nullable|string',
                'tipo' => 'nullable|string|in:Producto,Servicio',
                'enable' => 'nullable|string|in:0,1',
                'con_stock' => 'nullable|boolean',
                'stock_minimo' => 'nullable|boolean', // Productos con stock bajo el mínimo
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:200',
                'order_by' => 'nullable|string|in:nombre,codigo,precio,costo,created_at',
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
            $query = Producto::withoutGlobalScopes()
                            ->where('id_empresa', $empresa->id)
                            ->with(['inventarios' => function($query) {
                                $query->whereHas('bodega', function($q) {
                                    $q->where('activo', 1);
                                });
                            }]);

            // Aplicar filtros
            if ($request->filled('codigo')) {
                $query->where('codigo', 'like', '%' . $request->codigo . '%');
            }

            if ($request->filled('nombre')) {
                $query->where('nombre', 'like', '%' . $request->nombre . '%');
            }

            if ($request->filled('marca')) {
                $query->where('marca', 'like', '%' . $request->marca . '%');
            }

            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->filled('enable')) {
                $query->where('enable', $request->enable);
            }

            // Filtro por categoría
            if ($request->filled('categoria')) {
                $query->whereHas('categoria', function($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->categoria . '%');
                });
            }

            // Filtro por productos con stock
            if ($request->boolean('con_stock')) {
                $query->whereHas('inventarios', function($q) {
                    $q->where('stock', '>', 0);
                });
            }

            // Filtro por productos con stock bajo el mínimo
            if ($request->boolean('stock_minimo')) {
                $query->whereHas('inventarios', function($q) {
                    $q->whereRaw('stock < stock_minimo');
                });
            }

            // Aplicar ordenamiento
            $orderBy = $request->get('order_by', 'nombre');
            $orderDirection = $request->get('order_direction', 'asc');
            $query->orderBy($orderBy, $orderDirection);

            // Ejecutar consulta paginada
            $productos = $query->paginate($perPage, ['*'], 'page', $page);

            // Log de consulta exitosa
            Log::info('Consulta API externa de inventario exitosa', [
                'empresa_id' => $empresa->id,
                'filtros' => $request->only(['codigo', 'nombre', 'categoria', 'marca', 'tipo', 'con_stock', 'stock_minimo']),
                'resultados' => $productos->count(),
                'pagina' => $page,
                'por_pagina' => $perPage
            ]);

            return response()->json([
                'success' => true,
                'data' => InventoryResource::collection($productos->items()),
                'pagination' => [
                    'current_page' => $productos->currentPage(),
                    'per_page' => $productos->perPage(),
                    'total' => $productos->total(),
                    'total_pages' => $productos->lastPage(),
                    'has_next' => $productos->hasMorePages(),
                    'has_prev' => $productos->currentPage() > 1,
                    'from' => $productos->firstItem(),
                    'to' => $productos->lastItem()
                ],
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString(),
                    'filters_applied' => $request->only(['codigo', 'nombre', 'categoria', 'marca', 'tipo', 'con_stock', 'stock_minimo'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de inventario', [
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
     * Obtener un producto específico con su inventario
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productoId
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $productoId)
    {
        try {
            // Obtener empresa desde el middleware
            $empresa = $request->attributes->get('empresa');

            // Buscar el producto
            $producto = Producto::withoutGlobalScopes()
                              ->where('id_empresa', $empresa->id)
                              ->where('id', $productoId)
                              ->with(['inventarios' => function($query) {
                                  $query->whereHas('bodega', function($q) {
                                      $q->where('activo', 1);
                                  });
                              }])
                              ->first();

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'error' => 'Producto no encontrado',
                    'code' => 404
                ], 404);
            }

            // Log de consulta exitosa
            Log::info('Consulta API externa de producto individual exitosa', [
                'empresa_id' => $empresa->id,
                'producto_id' => $productoId
            ]);

            return response()->json([
                'success' => true,
                'data' => new InventoryResource($producto),
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en consulta API externa de producto individual', [
                'empresa_id' => $request->attributes->get('empresa')->id ?? null,
                'producto_id' => $productoId,
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
     * Obtener resumen de inventario
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function summary(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'categoria' => 'nullable|string',
                'tipo' => 'nullable|string|in:Producto,Servicio',
                'enable' => 'nullable|string|in:0,1',
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
            
            // Construir query base para productos
            $queryProductos = Producto::withoutGlobalScopes()->where('id_empresa', $empresa->id);

            // Aplicar filtros
            if ($request->filled('tipo')) {
                $queryProductos->where('tipo', $request->tipo);
            }

            if ($request->filled('enable')) {
                $queryProductos->where('enable', $request->enable);
            }

            if ($request->filled('categoria')) {
                $queryProductos->whereHas('categoria', function($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->categoria . '%');
                });
            }

            // Calcular resumen de productos
            $totalProductos = $queryProductos->count();
            $productosActivos = $queryProductos->where('enable', '1')->count();
            $productosInactivos = $queryProductos->where('enable', '0')->count();

            // Resumen de inventario (stock)
            $inventarioQuery = Inventario::whereHas('producto', function($q) use ($empresa) {
                $q->withoutGlobalScopes()->where('id_empresa', $empresa->id);
            })->whereHas('bodega', function($q) {
                $q->where('activo', 1);
            });

            // Aplicar filtros al inventario
            if ($request->filled('tipo') || $request->filled('enable') || $request->filled('categoria')) {
                $inventarioQuery->whereHas('producto', function($q) use ($request, $empresa) {
                    $q->withoutGlobalScopes()->where('id_empresa', $empresa->id);
                    
                    if ($request->filled('tipo')) {
                        $q->where('tipo', $request->tipo);
                    }
                    if ($request->filled('enable')) {
                        $q->where('enable', $request->enable);
                    }
                    if ($request->filled('categoria')) {
                        $q->whereHas('categoria', function($subQ) use ($request) {
                            $subQ->where('nombre', 'like', '%' . $request->categoria . '%');
                        });
                    }
                });
            }

            $stockTotal = $inventarioQuery->sum('stock');
            $valorInventario = $inventarioQuery->join('productos', 'inventario.id_producto', '=', 'productos.id')
                                              ->sum(DB::raw('inventario.stock * productos.costo'));
            
            $productosConStock = $inventarioQuery->where('stock', '>', 0)->distinct('id_producto')->count();
            $productosSinStock = $inventarioQuery->where('stock', '<=', 0)->distinct('id_producto')->count();
            $productosStockBajo = $inventarioQuery->whereRaw('stock < stock_minimo')->distinct('id_producto')->count();

            // Productos por categoría
            $productosPorCategoria = Producto::withoutGlobalScopes()
                ->where('id_empresa', $empresa->id)
                ->when($request->filled('tipo'), function($q) use ($request) {
                    return $q->where('tipo', $request->tipo);
                })
                ->when($request->filled('enable'), function($q) use ($request) {
                    return $q->where('enable', $request->enable);
                })
                ->join('categorias', 'productos.id_categoria', '=', 'categorias.id')
                ->selectRaw('categorias.nombre as categoria, COUNT(*) as cantidad')
                ->groupBy('categorias.nombre')
                ->get();

            $summary = [
                'productos' => [
                    'total' => $totalProductos,
                    'activos' => $productosActivos,
                    'inactivos' => $productosInactivos,
                ],
                'inventario' => [
                    'stock_total' => round($stockTotal, 2),
                    'valor_total' => round($valorInventario, 2),
                    'productos_con_stock' => $productosConStock,
                    'productos_sin_stock' => $productosSinStock,
                    'productos_stock_bajo' => $productosStockBajo,
                ],
                'productos_por_categoria' => $productosPorCategoria->map(function($item) {
                    return [
                        'categoria' => $item->categoria,
                        'cantidad' => $item->cantidad
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $summary,
                'meta' => [
                    'empresa' => $empresa->nombre,
                    'timestamp' => now()->toISOString(),
                    'filters_applied' => $request->only(['categoria', 'tipo', 'enable'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en resumen API externa de inventario', [
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


