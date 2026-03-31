<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Restaurante\PedidoRestauranteDetalle;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta as VentaModel;
use App\Services\Restaurante\PedidoRestauranteInventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PedidoRestauranteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $paginate = (int) $request->get('paginate', 10);
        if ($paginate < 1) {
            $paginate = 10;
        }
        if ($paginate > 100) {
            $paginate = 100;
        }

        $page = max(1, (int) $request->get('page', 1));

        $orden = $request->get('orden', 'fecha');
        $allowedOrden = ['fecha', 'id', 'total', 'estado'];
        if (! in_array($orden, $allowedOrden, true)) {
            $orden = 'fecha';
        }
        $direccion = strtolower((string) $request->get('direccion', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = PedidoRestaurante::where('restaurante_pedidos.id_empresa', $user->id_empresa)
            ->with(['cliente', 'usuario'])
            ->when($request->estado, fn ($q) => $q->where('restaurante_pedidos.estado', $request->estado))
            ->when($request->filled('canal'), fn ($q) => $q->where('restaurante_pedidos.canal', 'like', '%' . $request->canal . '%'))
            ->when($request->fecha_desde, fn ($q) => $q->whereDate('restaurante_pedidos.fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn ($q) => $q->whereDate('restaurante_pedidos.fecha', '<=', $request->fecha_hasta))
            ->when($request->id_sucursal, fn ($q) => $q->where('restaurante_pedidos.id_sucursal', $request->id_sucursal))
            ->when($request->filled('buscador'), function ($q) use ($request) {
                $term = trim((string) $request->buscador);
                if ($term === '') {
                    return;
                }
                $like = '%' . $term . '%';
                $q->where(function ($qq) use ($term, $like) {
                    if (ctype_digit($term)) {
                        $qq->where('restaurante_pedidos.id', (int) $term);
                    }
                    $qq->orWhere('restaurante_pedidos.canal', 'like', $like)
                        ->orWhere('restaurante_pedidos.referencia_externa', 'like', $like)
                        ->orWhereHas('cliente', function ($cq) use ($like) {
                            $cq->where('nombre_completo', 'like', $like)
                                ->orWhere('nombre_empresa', 'like', $like);
                        });
                });
            })
            ->orderBy('restaurante_pedidos.' . $orden, $direccion)
            ->orderByDesc('restaurante_pedidos.id');

        $pedidos = $query->paginate($paginate, ['restaurante_pedidos.*'], 'page', $page);

        return response()->json($pedidos);
    }

    public function imprimir(int $id): Response
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response('Usuario sin empresa asociada', 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)
            ->with(['detalles.producto', 'cliente', 'usuario'])
            ->findOrFail($id);

        $empresa = Empresa::find($user->id_empresa);

        return response()
            ->view('restaurante.pedido-canal-ticket', [
                'pedido' => $pedido,
                'empresa' => $empresa,
            ])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function confirmar(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pedido->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se pueden confirmar pedidos en borrador'], 422);
        }

        if ($pedido->detalles()->doesntExist()) {
            return response()->json(['error' => 'El pedido no tiene líneas de detalle'], 422);
        }

        DB::beginTransaction();
        try {
            $inv = new PedidoRestauranteInventarioService;
            $err = $inv->aplicarAlConfirmar($pedido->fresh(['detalles']), $user);
            if ($err) {
                DB::rollBack();

                return response()->json(['error' => $err], 422);
            }
            $pedido->update(['estado' => 'pendiente_facturar']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $pedido->load(['cliente', 'usuario']);

        return response()->json($pedido->fresh(['detalles.producto', 'cliente', 'usuario']));
    }

    public function anular(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if (! in_array($pedido->estado, ['borrador', 'pendiente_facturar'], true)) {
            return response()->json(['error' => 'Solo se pueden anular pedidos en borrador o pendiente de facturar'], 422);
        }

        DB::beginTransaction();
        try {
            if ($pedido->estado === 'pendiente_facturar' && $pedido->inventario_descontado_at) {
                $inv = new PedidoRestauranteInventarioService;
                $err = $inv->revertirPorAnulacion($pedido->fresh(['detalles']), $user);
                if ($err) {
                    DB::rollBack();

                    return response()->json(['error' => $err], 422);
                }
            }
            $pedido->refresh();
            $pedido->update(['estado' => 'anulado']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $pedido->load(['cliente', 'usuario']);

        return response()->json($pedido->fresh(['detalles.producto', 'cliente', 'usuario']));
    }

    /**
     * Payload para pantalla de facturación (productos, precios, cliente opcional), igual que pre-cuenta mesa.
     */
    public function prepararFactura(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)
            ->with(['detalles.producto', 'cliente'])
            ->findOrFail($id);

        if ($pedido->estado !== 'pendiente_facturar') {
            return response()->json(['error' => 'Solo se puede facturar un pedido en estado pendiente de facturar'], 422);
        }

        if ($pedido->id_venta) {
            return response()->json(['error' => 'Este pedido ya tiene una venta asociada'], 422);
        }

        if ($pedido->detalles->isEmpty()) {
            return response()->json(['error' => 'El pedido no tiene líneas de detalle'], 422);
        }

        $detalles = $pedido->detalles->map(fn ($d) => [
            'id_producto' => $d->producto_id,
            'cantidad' => (float) $d->cantidad,
            'precio' => (float) $d->precio,
            'descripcion' => $d->producto->nombre ?? '',
            'descuento' => (float) ($d->descuento ?? 0),
        ])->values()->toArray();

        return response()->json([
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'id_sucursal' => $pedido->id_sucursal,
            'id_bodega_inventario' => $pedido->id_bodega_inventario,
            'fecha' => $pedido->fecha?->format('Y-m-d'),
            'subtotal' => (float) $pedido->subtotal,
            'total' => (float) $pedido->total,
            'canal' => $pedido->canal,
            'referencia_externa' => $pedido->referencia_externa,
            'observaciones' => $pedido->observaciones,
            'detalles' => $detalles,
        ]);
    }

    public function marcarFacturado(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'venta_id' => 'required|integer|exists:ventas,id',
        ]);

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pedido->estado !== 'pendiente_facturar') {
            return response()->json(['error' => 'El pedido no está pendiente de facturar'], 422);
        }

        if ($pedido->id_venta) {
            return response()->json(['error' => 'Este pedido ya fue vinculado a una venta'], 422);
        }

        $ventaOk = VentaModel::where('id', $validated['venta_id'])
            ->where('id_empresa', $user->id_empresa)
            ->exists();

        if (! $ventaOk) {
            return response()->json(['error' => 'La venta no pertenece a esta empresa'], 422);
        }

        $pedido->update([
            'id_venta' => $validated['venta_id'],
            'estado' => 'facturado',
        ]);

        return response()->json($pedido->fresh(['detalles.producto', 'cliente', 'usuario', 'venta']));
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)
            ->with(['detalles.producto', 'cliente', 'usuario', 'venta'])
            ->findOrFail($id);

        return response()->json($pedido);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'fecha' => 'required|date',
            'canal' => 'nullable|string|max:100',
            'referencia_externa' => 'nullable|string|max:150',
            'cliente_id' => 'nullable|exists:clientes,id',
            'observaciones' => 'nullable|string|max:5000',
            'id_sucursal' => 'nullable|integer',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad' => 'required|numeric|min:0.0001',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.notas' => 'nullable|string|max:2000',
        ]);

        foreach ($validated['detalles'] as $row) {
            if (!$this->productoPerteneceEmpresa((int) $row['producto_id'], (int) $user->id_empresa)) {
                return response()->json(['error' => 'Producto no válido para la empresa: ' . $row['producto_id']], 422);
            }
        }

        if (!empty($validated['cliente_id'])) {
            $err = $this->validarClienteEmpresa((int) $validated['cliente_id'], (int) $user->id_empresa);
            if ($err) {
                return $err;
            }
        }

        $pedido = DB::transaction(function () use ($validated, $user) {
            $pedido = PedidoRestaurante::create([
                'id_empresa' => $user->id_empresa,
                'id_sucursal' => $validated['id_sucursal'] ?? $user->id_sucursal,
                'usuario_id' => $user->id,
                'fecha' => $validated['fecha'],
                'canal' => $validated['canal'] ?? null,
                'referencia_externa' => $validated['referencia_externa'] ?? null,
                'estado' => 'borrador',
                'cliente_id' => $validated['cliente_id'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'subtotal' => 0,
                'descuento' => 0,
                'total' => 0,
            ]);

            foreach ($validated['detalles'] as $row) {
                $this->crearDetalle($pedido, $row);
            }

            $this->recalcularTotales($pedido);

            return $pedido->fresh(['detalles.producto', 'cliente', 'usuario']);
        });

        return response()->json($pedido, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pedido->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se pueden editar pedidos en estado borrador'], 422);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|date',
            'canal' => 'nullable|string|max:100',
            'referencia_externa' => 'nullable|string|max:150',
            'cliente_id' => 'nullable|exists:clientes,id',
            'observaciones' => 'nullable|string|max:5000',
            'id_sucursal' => 'nullable|integer',
            'detalles' => 'sometimes|array|min:1',
            'detalles.*.producto_id' => 'required_with:detalles|integer',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.0001',
            'detalles.*.precio' => 'required_with:detalles|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.notas' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['detalles'])) {
            foreach ($validated['detalles'] as $row) {
                if (!$this->productoPerteneceEmpresa((int) $row['producto_id'], (int) $user->id_empresa)) {
                    return response()->json(['error' => 'Producto no válido para la empresa: ' . $row['producto_id']], 422);
                }
            }
        }

        if (!empty($validated['cliente_id'])) {
            $err = $this->validarClienteEmpresa((int) $validated['cliente_id'], (int) $user->id_empresa);
            if ($err) {
                return $err;
            }
        }

        DB::transaction(function () use ($validated, $pedido) {
            if (isset($validated['fecha'])) {
                $pedido->fecha = $validated['fecha'];
            }
            if (array_key_exists('canal', $validated)) {
                $pedido->canal = $validated['canal'];
            }
            if (array_key_exists('referencia_externa', $validated)) {
                $pedido->referencia_externa = $validated['referencia_externa'];
            }
            if (array_key_exists('cliente_id', $validated)) {
                $pedido->cliente_id = $validated['cliente_id'];
            }
            if (array_key_exists('observaciones', $validated)) {
                $pedido->observaciones = $validated['observaciones'];
            }
            if (array_key_exists('id_sucursal', $validated)) {
                $pedido->id_sucursal = $validated['id_sucursal'];
            }

            if (isset($validated['detalles'])) {
                $pedido->detalles()->delete();
                foreach ($validated['detalles'] as $row) {
                    $this->crearDetalle($pedido, $row);
                }
            }

            $pedido->save();
            $this->recalcularTotales($pedido);
        });

        $pedido->load(['detalles.producto', 'cliente', 'usuario']);

        return response()->json($pedido);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pedido->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se pueden eliminar pedidos en estado borrador'], 422);
        }

        $pedido->delete();

        return response()->json(['ok' => true]);
    }

    private function crearDetalle(PedidoRestaurante $pedido, array $row): void
    {
        $cantidad = (float) $row['cantidad'];
        $precio = (float) $row['precio'];
        $descuento = (float) ($row['descuento'] ?? 0);
        $subtotal = round($cantidad * $precio, 4);
        $total = round(max(0, $subtotal - $descuento), 4);

        PedidoRestauranteDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => (int) $row['producto_id'],
            'cantidad' => $cantidad,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'total' => $total,
            'notas' => $row['notas'] ?? null,
        ]);
    }

    private function recalcularTotales(PedidoRestaurante $pedido): void
    {
        $pedido->load('detalles');
        $subtotal = 0;
        $descuento = 0;
        $total = 0;
        foreach ($pedido->detalles as $d) {
            $subtotal += (float) $d->subtotal;
            $descuento += (float) $d->descuento;
            $total += (float) $d->total;
        }
        $pedido->subtotal = round($subtotal, 4);
        $pedido->descuento = round($descuento, 4);
        $pedido->total = round($total, 4);
        $pedido->save();
    }

    private function productoPerteneceEmpresa(int $productoId, int $idEmpresa): bool
    {
        return Producto::where('id', $productoId)->where('id_empresa', $idEmpresa)->exists();
    }

    private function validarClienteEmpresa(int $clienteId, int $idEmpresa): ?JsonResponse
    {
        $ok = Cliente::where('id', $clienteId)->where('id_empresa', $idEmpresa)->exists();
        if (!$ok) {
            return response()->json(['error' => 'Cliente no válido para la empresa'], 422);
        }

        return null;
    }
}
