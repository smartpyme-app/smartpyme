<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Paquete;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Restaurante\PedidoRestauranteDetalle;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta as VentaModel;
use App\Services\Restaurante\PedidoCanalInventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
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
            ->with(['cliente', 'usuario', 'detalles'])
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

        return response()->json($this->enrichPaginatedPayload($pedidos->toArray(), (int) $user->id_empresa));
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

    public function enviarComanda(int $id): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)
            ->with(['detalles.producto'])
            ->findOrFail($id);

        if (! in_array($pedido->estado, ['borrador', 'pendiente_facturar'], true)) {
            return response()->json(['error' => 'No se puede enviar comanda en este estado'], 422);
        }

        $itemsCocina = [];
        $itemsBarra = [];

        foreach ($pedido->detalles as $item) {
            $producto = $item->producto;
            if (! $producto || ! $producto->genera_comanda) {
                continue;
            }
            $dest = strtolower(trim((string) ($producto->destino_comanda ?? 'cocina')));
            if (in_array($dest, ['barra', 'ambos'], true) && ! $item->enviado_barra) {
                $itemsBarra[] = $item;
            }
            if (in_array($dest, ['cocina', 'ambos'], true) && ! $item->enviado_cocina) {
                $itemsCocina[] = $item;
            }
        }

        if ($itemsCocina === [] && $itemsBarra === []) {
            return response()->json(['error' => 'No hay ítems pendientes por enviar a cocina/barra'], 422);
        }

        $comandasCreadas = [];
        $base = Comanda::where('pedido_id', $pedido->id)->count();
        $n = $base;

        DB::beginTransaction();
        try {
            if ($itemsCocina !== []) {
                $n++;
                $comanda = Comanda::create([
                    'pedido_id' => $pedido->id,
                    'numero_comanda' => "P-{$pedido->id}-{$n}-C",
                    'estado' => 'pendiente',
                    'destino' => 'cocina',
                    'enviado_at' => now(),
                ]);
                foreach ($itemsCocina as $item) {
                    ComandaDetalle::create([
                        'comanda_id' => $comanda->id,
                        'pedido_detalle_id' => $item->id,
                    ]);
                    $item->update(['enviado_cocina' => true]);
                }
                $comandasCreadas[] = $comanda->load(['detalles.pedidoDetalle.producto']);
            }
            if ($itemsBarra !== []) {
                $n++;
                $comanda = Comanda::create([
                    'pedido_id' => $pedido->id,
                    'numero_comanda' => "P-{$pedido->id}-{$n}-B",
                    'estado' => 'pendiente',
                    'destino' => 'barra',
                    'enviado_at' => now(),
                ]);
                foreach ($itemsBarra as $item) {
                    ComandaDetalle::create([
                        'comanda_id' => $comanda->id,
                        'pedido_detalle_id' => $item->id,
                    ]);
                    $item->update(['enviado_barra' => true]);
                }
                $comandasCreadas[] = $comanda->load(['detalles.pedidoDetalle.producto']);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'comandas' => $comandasCreadas,
            'primera' => $comandasCreadas[0] ?? null,
        ], 201);
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $request->validate([
            'id_bodega' => 'nullable|integer',
        ]);

        $pedido = PedidoRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pedido->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se pueden confirmar pedidos en borrador'], 422);
        }

        if ($pedido->detalles()->count() === 0) {
            return response()->json(['error' => 'El pedido no tiene líneas para confirmar'], 422);
        }

        if ($request->filled('id_bodega')) {
            $err = $this->validarBodegaEmpresa((int) $request->id_bodega, (int) $user->id_empresa);
            if ($err) {
                return $err;
            }
        }

        if ($pedido->id_bodega) {
            $err = $this->validarBodegaEmpresa((int) $pedido->id_bodega, (int) $user->id_empresa);
            if ($err) {
                return $err;
            }
        }

        try {
            DB::transaction(function () use ($request, $pedido, $user) {
                $idBodega = (int) ($request->input('id_bodega') ?: $pedido->id_bodega ?: $user->id_bodega);
                if ($idBodega <= 0) {
                    throw new RuntimeException('Indique una bodega en el pedido o al confirmar para descontar inventario.');
                }
                $errBodega = $this->validarBodegaEmpresa($idBodega, (int) $user->id_empresa);
                if ($errBodega) {
                    throw new RuntimeException('Bodega no válida para la empresa.');
                }

                $pedido->id_bodega = $idBodega;
                $pedido->save();

                $svc = new PedidoCanalInventarioService();
                $svc->aplicarSalidasAlConfirmar($pedido->fresh(['detalles']), $idBodega);

                $pedido->update(['estado' => 'pendiente_facturar']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $pedido->load(['detalles.producto', 'cliente', 'usuario', 'venta']);

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response);
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

        try {
            DB::transaction(function () use ($pedido) {
                if ($pedido->estado === 'pendiente_facturar' && $pedido->id_bodega) {
                    $svc = new PedidoCanalInventarioService();
                    $svc->revertirSalidasPedido($pedido->fresh(['detalles']), (int) $pedido->id_bodega);
                }
                $pedido->update(['estado' => 'anulado']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $pedido->load(['detalles.producto', 'cliente', 'usuario', 'venta']);

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response);
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

        $detalles = $pedido->detalles->map(function ($d) use ($user) {
            $empresa = Empresa::find($user->id_empresa);
            $ivaEmpresa = $empresa ? max(0, (float) ($empresa->iva ?? 0)) : 0;
            $pct = $d->producto->porcentaje_impuesto ?? $ivaEmpresa;
            $pct = max(0, (float) $pct);
            $precioSinIva = (float) $d->precio;
            $precioConIva = $pct > 0 ? round($precioSinIva * (1 + $pct / 100), 4) : $precioSinIva;

            return [
                'id_producto' => $d->producto_id,
                'id_paquete' => $d->id_paquete,
                'cantidad' => (float) $d->cantidad,
                'precio' => $precioSinIva,
                'precio_con_iva' => $precioConIva,
                'porcentaje_impuesto' => $pct,
                'descripcion' => $d->producto->nombre ?? '',
                'descuento' => (float) ($d->descuento ?? 0),
            ];
        })->values()->toArray();

        return response()->json([
            'pedido_id' => $pedido->id,
            'cliente_id' => $pedido->cliente_id,
            'id_sucursal' => $pedido->id_sucursal,
            'id_bodega' => $pedido->id_bodega,
            'fecha' => $pedido->fecha ? $pedido->fecha->format('Y-m-d') : null,
            'subtotal' => (float) $pedido->subtotal,
            'total' => (float) $pedido->total,
            'precios_sin_iva' => true,
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

        // ponytail: mark associated packages as Facturado
        $paqueteIds = $pedido->detalles()->whereNotNull('id_paquete')->pluck('id_paquete');
        if ($paqueteIds->isNotEmpty()) {
            Paquete::whereIn('id', $paqueteIds)->update([
                'estado' => 'Facturado',
                'id_venta' => $validated['venta_id']
            ]);
        }

        $pedido->load(['detalles.producto', 'cliente', 'usuario', 'venta']);

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response);
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

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response);
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
            'id_bodega' => 'nullable|integer',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.id_paquete' => 'nullable|integer',
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

        if (!empty($validated['id_bodega'])) {
            $err = $this->validarBodegaEmpresa((int) $validated['id_bodega'], (int) $user->id_empresa);
            if ($err) {
                return $err;
            }
        }

        $pedido = DB::transaction(function () use ($validated, $user) {
            $pedido = PedidoRestaurante::create([
                'id_empresa' => $user->id_empresa,
                'id_sucursal' => $validated['id_sucursal'] ?? $user->id_sucursal,
                'id_bodega' => $validated['id_bodega'] ?? $user->id_bodega,
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

            return $pedido->fresh(['detalles.producto', 'cliente', 'usuario', 'venta']);
        });

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response, 201);
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
            'id_bodega' => 'nullable|integer',
            'detalles' => 'sometimes|array|min:1',
            'detalles.*.producto_id' => 'required_with:detalles|integer',
            'detalles.*.id_paquete' => 'nullable|integer',
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

        if (!empty($validated['id_bodega'])) {
            $err = $this->validarBodegaEmpresa((int) $validated['id_bodega'], (int) $user->id_empresa);
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
            if (array_key_exists('id_bodega', $validated)) {
                $pedido->id_bodega = $validated['id_bodega'];
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

        $pedido->load(['detalles.producto', 'cliente', 'usuario', 'venta']);

        $response = $this->enrichDetallesWithPaquetes($pedido->toArray(), $user->id_empresa);

        return response()->json($response);
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

        $idPaquete = $row['id_paquete'] ?? null;
        $notas = $row['notas'] ?? null;

        // ponytail: auto-resolve id_paquete if empty but notes has WR code
        if (empty($idPaquete) && !empty($notas)) {
            if (preg_match('/Número:\s*(\S+)/', $notas, $m)) {
                $wr = $m[1];
                $paquete = Paquete::where('wr', $wr)->first();
                if ($paquete) {
                    $idPaquete = $paquete->id;
                }
            }
        }

        PedidoRestauranteDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => (int) $row['producto_id'],
            'id_paquete' => $idPaquete,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => $subtotal,
            'total' => $total,
            'notas' => $notas,
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

    private function validarBodegaEmpresa(int $bodegaId, int $idEmpresa): ?JsonResponse
    {
        $ok = Bodega::withoutGlobalScopes()->where('id', $bodegaId)->where('id_empresa', $idEmpresa)->exists();
        if (!$ok) {
            return response()->json(['error' => 'Bodega no válida para la empresa'], 422);
        }

        return null;
    }

    private function enrichPaginatedPayload(array $payload, int $idEmpresa): array
    {
        foreach ($payload['data'] as $i => $row) {
            $payload['data'][$i] = $this->enrichDetallesWithPaquetes($row, $idEmpresa);
        }

        return $payload;
    }

    private function enrichDetallesWithPaquetes(array $response, int $idEmpresa): array
    {
        // ponytail: enrich detalles with paquete → boxfulShipment → parcels via id_paquete or fallback to WR number in notas
        $wrMap = []; // detalle index => wr
        $paqueteIds = []; // detalle index => id_paquete

        foreach ($response['detalles'] as $i => $det) {
            if (!empty($det['id_paquete'])) {
                $paqueteIds[$i] = $det['id_paquete'];
            } elseif (preg_match('/Número:\s*(\S+)/', $det['notas'] ?? '', $m)) {
                $wrMap[$i] = $m[1];
            }
        }

        if (!empty($paqueteIds) || !empty($wrMap)) {
            $paquetesMap = collect();

            if (!empty($paqueteIds)) {
                $paquetesById = Paquete::whereIn('id', array_values($paqueteIds))
                    ->where('id_empresa', $idEmpresa)
                    ->with('boxfulShipment.parcels')
                    ->get()
                    ->keyBy('id');
                foreach ($paqueteIds as $i => $id) {
                    $response['detalles'][$i]['paquete'] = $paquetesById[$id] ?? null;
                }
            }

            if (!empty($wrMap)) {
                $paquetesByWr = Paquete::whereIn('wr', array_values($wrMap))
                    ->where('id_empresa', $idEmpresa)
                    ->with('boxfulShipment.parcels')
                    ->get()
                    ->keyBy('wr');
                foreach ($wrMap as $i => $wr) {
                    $response['detalles'][$i]['paquete'] = $paquetesByWr[$wr] ?? null;
                }
            }
        }

        $response['boxful_shipment'] = $this->resolveBoxfulShipmentFromDetalles($response['detalles'] ?? []);

        return $response;
    }

    /** Primer envío Boxful válido ligado a paquetes del pedido (vía detalles). */
    private function resolveBoxfulShipmentFromDetalles(array $detalles): ?array
    {
        $fallback = null;

        foreach ($detalles as $det) {
            $paquete = $this->paqueteToArray($det['paquete'] ?? null);
            if (! $paquete) {
                continue;
            }
            $bs = $paquete['boxful_shipment'] ?? $paquete['boxfulShipment'] ?? null;
            if (! $bs) {
                continue;
            }
            $row = is_array($bs) ? $bs : (method_exists($bs, 'toArray') ? $bs->toArray() : null);
            if (! $row) {
                continue;
            }
            if (! empty($row['shipment_number']) || ! empty($row['boxful_shipment_id'])) {
                return $row;
            }
            $fallback ??= $row;
        }

        return $fallback;
    }

    private function paqueteToArray($paquete): ?array
    {
        if ($paquete === null) {
            return null;
        }
        if (is_array($paquete)) {
            return $paquete;
        }
        if (is_object($paquete) && method_exists($paquete, 'toArray')) {
            return $paquete->toArray();
        }

        return null;
    }
}
