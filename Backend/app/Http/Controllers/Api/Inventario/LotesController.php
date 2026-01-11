<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Bodega;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LotesController extends Controller
{
    /**
     * Listar todos los lotes con filtros
     */
    public function index(Request $request)
    {
        $query = Lote::with(['producto', 'bodega'])
            ->when($request->id_producto, function ($q) use ($request) {
                return $q->where('id_producto', $request->id_producto);
            })
            ->when($request->id_bodega, function ($q) use ($request) {
                return $q->where('id_bodega', $request->id_bodega);
            })
            ->when($request->numero_lote, function ($q) use ($request) {
                return $q->where('numero_lote', 'like', '%' . $request->numero_lote . '%');
            })
            ->when($request->vencimiento_proximo, function ($q) use ($request) {
                $dias = $request->dias_anticipacion ?? 30;
                return $q->whereNotNull('fecha_vencimiento')
                    ->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)]);
            })
            ->when($request->vencidos, function ($q) {
                return $q->whereNotNull('fecha_vencimiento')
                    ->where('fecha_vencimiento', '<', now());
            })
            ->when($request->con_stock, function ($q) {
                return $q->where('stock', '>', 0);
            })
            ->when($request->sin_stock, function ($q) {
                return $q->where('stock', '<=', 0);
            });

        // Ordenamiento
        $sortBy = $request->orden ?? $request->sort_by ?? 'created_at';
        $sortOrder = $request->direccion ?? $request->sort_order ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->paginate ?? $request->per_page ?? 10;
        if ($perPage) {
            return $query->paginate($perPage);
        }

        return response()->json($query->get(), 200);
    }

    /**
     * Obtener un lote específico
     */
    public function show($id)
    {
        $lote = Lote::with(['producto', 'bodega', 'detallesCompra', 'detallesVenta'])->findOrFail($id);
        return response()->json($lote, 200);
    }

    /**
     * Crear un nuevo lote
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|exists:productos,id',
            'id_bodega' => 'required|exists:sucursal_bodegas,id',
            'numero_lote' => 'nullable|string|max:255',
            'fecha_vencimiento' => 'nullable|date',
            'fecha_fabricacion' => 'nullable|date',
            'stock' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Verificar que el producto tenga inventario por lotes habilitado
        $producto = Producto::findOrFail($request->id_producto);
        if (!$producto->inventario_por_lotes) {
            return response()->json([
                'error' => 'El producto no tiene inventario por lotes habilitado'
            ], 400);
        }

        $lote = Lote::create([
            'id_producto' => $request->id_producto,
            'id_bodega' => $request->id_bodega,
            'numero_lote' => $request->numero_lote,
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'fecha_fabricacion' => $request->fecha_fabricacion,
            'stock' => $request->stock,
            'stock_inicial' => $request->stock,
            'id_empresa' => Auth::user()->id_empresa,
            'observaciones' => $request->observaciones,
        ]);

        return response()->json($lote->load(['producto', 'bodega']), 201);
    }

    /**
     * Actualizar un lote
     */
    public function update(Request $request, $id)
    {
        $lote = Lote::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'numero_lote' => 'nullable|string|max:255',
            'fecha_vencimiento' => 'nullable|date',
            'fecha_fabricacion' => 'nullable|date',
            'stock' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $lote->update($request->only([
            'numero_lote',
            'fecha_vencimiento',
            'fecha_fabricacion',
            'stock',
            'observaciones',
        ]));

        return response()->json($lote->load(['producto', 'bodega']), 200);
    }

    /**
     * Eliminar un lote (soft delete)
     */
    public function destroy($id)
    {
        $lote = Lote::findOrFail($id);

        // Verificar que no tenga stock antes de eliminar
        if ($lote->stock > 0) {
            return response()->json([
                'error' => 'No se puede eliminar un lote que tiene stock disponible'
            ], 400);
        }

        $lote->delete();

        return response()->json(['message' => 'Lote eliminado correctamente'], 200);
    }

    /**
     * Obtener lotes de un producto específico
     */
    public function getByProducto($productoId)
    {
        $lotes = Lote::where('id_producto', $productoId)
            ->with(['bodega'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        return response()->json($lotes, 200);
    }

    /**
     * Obtener lotes disponibles para descarga (con stock > 0)
     */
    public function getDisponibles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|exists:productos,id',
            'id_bodega' => 'required|exists:sucursal_bodegas,id',
            'cantidad' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $lotes = Lote::where('id_producto', $request->id_producto)
            ->where('id_bodega', $request->id_bodega)
            ->where('stock', '>', 0)
            ->with(['producto', 'bodega'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        // Si se especifica una cantidad, filtrar lotes que tengan suficiente stock
        if ($request->cantidad) {
            $lotes = $lotes->filter(function ($lote) use ($request) {
                return $lote->stock >= $request->cantidad;
            });
        }

        return response()->json($lotes->values(), 200);
    }

    /**
     * Obtener lotes próximos a vencer
     */
    public function getProximosAVencer(Request $request)
    {
        $dias = $request->dias_anticipacion ?? 30;
        
        $lotes = Lote::whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)])
            ->where('stock', '>', 0)
            ->with(['producto', 'bodega'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        return response()->json($lotes, 200);
    }

    /**
     * Obtener lotes vencidos
     */
    public function getVencidos()
    {
        $lotes = Lote::where('id_empresa', Auth::user()->id_empresa)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->where('stock', '>', 0)
            ->with(['producto', 'bodega'])
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();

        return response()->json($lotes, 200);
    }

    /**
     * Obtener estadísticas de lotes
     */
    public function getEstadisticas(Request $request)
    {
        $query = Lote::where('id_empresa', Auth::user()->id_empresa);

        // Aplicar filtros opcionales
        if ($request->id_bodega) {
            $query->where('id_bodega', $request->id_bodega);
        }

        $total = (clone $query)->count();
        $vencidos = (clone $query)->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now())
            ->where('stock', '>', 0)
            ->count();
        
        $diasAnticipacion = $request->dias_anticipacion ?? 30;
        $proximosAVencer = (clone $query)->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays($diasAnticipacion)])
            ->where('stock', '>', 0)
            ->count();
        
        $conStock = (clone $query)->where('stock', '>', 0)->count();
        $sinStock = (clone $query)->where('stock', '<=', 0)->count();

        return response()->json([
            'total' => $total,
            'vencidos' => $vencidos,
            'proximos_a_vencer' => $proximosAVencer,
            'con_stock' => $conStock,
            'sin_stock' => $sinStock
        ], 200);
    }
}
