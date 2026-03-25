<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Restaurante\PedidoRestauranteDetalle;
use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoRestauranteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $query = PedidoRestaurante::where('id_empresa', $user->id_empresa)
            ->with(['cliente', 'usuario'])
            ->when($request->estado, fn ($q) => $q->where('estado', $request->estado))
            ->when($request->canal, fn ($q) => $q->where('canal', 'like', '%' . $request->canal . '%'))
            ->when($request->fecha_desde, fn ($q) => $q->whereDate('fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn ($q) => $q->whereDate('fecha', '<=', $request->fecha_hasta))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $pedidos = $query->limit(500)->get();

        return response()->json($pedidos);
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
