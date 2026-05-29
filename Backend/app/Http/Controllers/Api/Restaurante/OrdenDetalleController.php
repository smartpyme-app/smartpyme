<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\ItemEliminacionLog;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Services\Restaurante\RestauranteAutorizacionService;
use App\Services\Restaurante\RestauranteStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdenDetalleController extends Controller
{
    public function __construct(
        private RestauranteStockService $stockService,
        private RestauranteAutorizacionService $autorizacionService,
    ) {}

    private function normalizarNotas(?string $notasRaw): ?string
    {
        if ($notasRaw === null || ! is_string($notasRaw)) {
            return null;
        }
        $t = trim($notasRaw);

        return $t === '' ? null : $t;
    }

    /**
     * Respuesta de error de stock o null si OK.
     */
    private function errorStockSiAplica(Producto $producto, SesionMesa $sesion, float $cantidad): ?JsonResponse
    {
        $user = auth()->user();
        $empresa = Empresa::find($user->id_empresa);
        if (! $empresa) {
            return null;
        }
        $idBodega = $this->stockService->resolverIdBodega($sesion, $user);
        if (! $idBodega) {
            return null;
        }
        $v = $this->stockService->validarDisponibilidad($producto, $idBodega, $cantidad, $empresa);
        if (! $v['ok']) {
            return response()->json(['error' => $v['mensaje']], 422);
        }

        return null;
    }

    private function crearComandaEliminado(
        SesionMesa $sesion,
        OrdenDetalle $item,
        bool $itemHabiaSidoEnviado,
        string $motivoCodigo,
        ?string $motivoDetalle,
    ): Comanda {
        $sesion->loadMissing('mesa');
        $numeroMesa = $sesion->mesa->numero ?? '?';
        $correlativo = Comanda::where('sesion_id', $sesion->id)->count() + 1;

        $comanda = Comanda::create([
            'sesion_id' => $sesion->id,
            'numero_comanda' => "DEL-{$numeroMesa}-{$correlativo}",
            'estado' => 'pendiente',
            'destino' => 'eliminacion',
            'eliminacion_item_enviado' => $itemHabiaSidoEnviado,
            'motivo_eliminacion_codigo' => $motivoCodigo,
            'motivo_eliminacion_detalle' => $motivoDetalle,
            'enviado_at' => now(),
        ]);

        ComandaDetalle::create([
            'comanda_id' => $comanda->id,
            'orden_detalle_id' => $item->id,
        ]);

        return $comanda->load(['detalles.ordenDetalle' => fn ($q) => $q->withTrashed()->with('producto')]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->findOrFail($id);

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'cantidad' => 'required|numeric|min:0.01',
            'notas' => 'nullable|string|max:255',
        ]);

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $user->id_empresa)
            ->findOrFail($validated['producto_id']);

        $precioLista = round((float) ($producto->precio ?? 0), 2);
        $notas = $this->normalizarNotas($validated['notas'] ?? null);
        $nuevaCantidadReq = (float) $validated['cantidad'];

        $lineasFusionables = OrdenDetalle::where('sesion_id', $sesion->id)
            ->where('producto_id', $producto->id)
            ->whereRaw('ROUND(precio_unitario, 2) = ?', [$precioLista])
            ->where('enviado_cocina', false)
            ->where('enviado_barra', false)
            ->where(function ($q) use ($notas) {
                if ($notas === null) {
                    $q->whereNull('notas')->orWhere('notas', '');
                } else {
                    $q->where('notas', $notas);
                }
            })
            ->orderBy('id')
            ->get();

        if ($lineasFusionables->isNotEmpty()) {
            $principal = $lineasFusionables->first();
            $cantidadTotal = (float) $lineasFusionables->sum(fn ($l) => (float) $l->cantidad) + $nuevaCantidadReq;

            $err = $this->errorStockSiAplica($producto, $sesion, $cantidadTotal);
            if ($err) {
                return $err;
            }

            $principal->update([
                'cantidad' => $cantidadTotal,
                'precio_unitario' => $precioLista,
            ]);

            $idsExtra = $lineasFusionables->skip(1)->pluck('id')->all();
            if ($idsExtra !== []) {
                OrdenDetalle::whereIn('id', $idsExtra)->forceDelete();
            }

            return response()->json($principal->fresh()->load('producto'), 200);
        }

        $errNuevo = $this->errorStockSiAplica($producto, $sesion, $nuevaCantidadReq);
        if ($errNuevo) {
            return $errNuevo;
        }

        $item = OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => $validated['cantidad'],
            'precio_unitario' => $precioLista,
            'notas' => $notas,
            'enviado_cocina' => false,
            'enviado_barra' => false,
        ]);

        return response()->json($item->load('producto'), 201);
    }

    public function update(Request $request, int $sesionId, int $itemId): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)->findOrFail($sesionId);
        $item = OrdenDetalle::where('sesion_id', $sesion->id)->findOrFail($itemId);

        $validated = $request->validate([
            'cantidad' => 'sometimes|numeric|min:0.01',
            'notas' => 'nullable|string|max:255',
        ]);

        if (isset($validated['cantidad'])) {
            $producto = Producto::withoutGlobalScope('empresa')
                ->where('id_empresa', $user->id_empresa)
                ->findOrFail($item->producto_id);
            $err = $this->errorStockSiAplica($producto, $sesion, (float) $validated['cantidad']);
            if ($err) {
                return $err;
            }
        }

        $item->update($validated);

        return response()->json($item->fresh('producto'));
    }

    public function eliminar(Request $request, int $sesionId, int $itemId): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->findOrFail($sesionId);

        $item = OrdenDetalle::where('sesion_id', $sesion->id)->findOrFail($itemId);

        $validated = $request->validate([
            'motivo_codigo' => 'required|string|max:50',
            'motivo_detalle' => 'nullable|string|max:500',
        ]);

        $fueEnviado = $item->enviado_cocina || $item->enviado_barra;
        if ($fueEnviado && ! $this->autorizacionService->usuarioPuedeAutorizarOperaciones($user)) {
            return response()->json([
                'error' => 'Este ítem ya fue enviado a cocina o barra. Se requiere un usuario con perfil de gerencia o administrador para anularlo.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            ItemEliminacionLog::create([
                'orden_detalle_id' => $item->id,
                'sesion_id' => $sesion->id,
                'producto_id' => $item->producto_id,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'notas' => $item->notas,
                'enviado_cocina' => $item->enviado_cocina,
                'enviado_barra' => $item->enviado_barra,
                'motivo_codigo' => $validated['motivo_codigo'],
                'motivo_detalle' => $validated['motivo_detalle'] ?? null,
                'usuario_id' => $user->id,
                'autorizado_usuario_id' => $fueEnviado ? $user->id : null,
            ]);

            $comandaElim = $this->crearComandaEliminado(
                $sesion,
                $item,
                $fueEnviado,
                $validated['motivo_codigo'],
                $validated['motivo_detalle'] ?? null,
            );

            $item->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'ok' => true,
            'comanda_eliminacion' => $comandaElim,
        ]);
    }

    /**
     * @deprecated Usar POST .../eliminar con motivo
     */
    public function destroy(int $sesionId, int $itemId): JsonResponse
    {
        return response()->json([
            'error' => 'Use POST con motivo: restaurante/sesiones-mesa/{sesionId}/items/{itemId}/eliminar',
        ], 400);
    }
}
