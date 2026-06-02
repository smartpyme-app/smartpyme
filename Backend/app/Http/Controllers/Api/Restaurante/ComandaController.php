<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComandaController extends Controller
{
    private function normalizarDestino(?string $dest): string
    {
        $d = strtolower(trim((string) $dest));
        if (in_array($d, ['barra', 'ambos'], true)) {
            return $d;
        }

        return 'cocina';
    }

    private function itemPendienteCocina(OrdenDetalle $item, Producto $producto): bool
    {
        if (! $producto->genera_comanda) {
            return false;
        }
        $dest = $this->normalizarDestino($producto->destino_comanda);
        if ($dest === 'barra') {
            return false;
        }

        return ! $item->enviado_cocina;
    }

    private function itemPendienteBarra(OrdenDetalle $item, Producto $producto): bool
    {
        if (! $producto->genera_comanda) {
            return false;
        }
        $dest = $this->normalizarDestino($producto->destino_comanda);
        if ($dest === 'cocina') {
            return false;
        }

        return ! $item->enviado_barra;
    }

    /**
     * @param  OrdenDetalle[]  $items
     */
    private function crearComandaSesion(SesionMesa $sesion, string $destino, array $items, int $correlativo): ?Comanda
    {
        if ($items === []) {
            return null;
        }

        $mesa = $sesion->mesa ?? $sesion->mesa()->first();
        $numeroMesa = $mesa->numero ?? '?';
        $suf = $destino === 'barra' ? 'B' : 'C';

        $comanda = Comanda::create([
            'sesion_id' => $sesion->id,
            'numero_comanda' => "C-{$numeroMesa}-{$correlativo}-{$suf}",
            'estado' => 'pendiente',
            'destino' => $destino,
            'enviado_at' => now(),
        ]);

        foreach ($items as $item) {
            ComandaDetalle::create([
                'comanda_id' => $comanda->id,
                'orden_detalle_id' => $item->id,
            ]);
            if ($destino === 'cocina') {
                $item->update(['enviado_cocina' => true]);
            } else {
                $item->update(['enviado_barra' => true]);
            }
        }

        $comanda->load(['detalles.ordenDetalle.producto']);

        return $comanda;
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $comandas = Comanda::where(function ($q) use ($user) {
            $q->whereHas('sesion', fn ($sq) => $sq->where('id_empresa', $user->id_empresa))
                ->orWhereHas('pedido', fn ($pq) => $pq->where('id_empresa', $user->id_empresa));
        })
            ->whereIn('estado', ['pendiente', 'preparando', 'listo'])
            ->with([
                'sesion.mesa',
                'pedido',
                'detalles.ordenDetalle' => fn ($q) => $q->withTrashed()->with('producto'),
                'detalles.pedidoDetalle.producto',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comandas);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->with('mesa')
            ->findOrFail($id);

        $pendientes = OrdenDetalle::where('sesion_id', $sesion->id)
            ->with('producto')
            ->get();

        $itemsCocina = [];
        $itemsBarra = [];

        foreach ($pendientes as $item) {
            $producto = $item->producto;
            if (! $producto) {
                continue;
            }
            if ($this->itemPendienteCocina($item, $producto)) {
                $itemsCocina[] = $item;
            }
            if ($this->itemPendienteBarra($item, $producto)) {
                $itemsBarra[] = $item;
            }
        }

        if ($itemsCocina === [] && $itemsBarra === []) {
            return response()->json(['error' => 'No hay ítems pendientes por enviar'], 422);
        }

        $comandasCreadas = [];
        $base = Comanda::where('sesion_id', $sesion->id)->count();
        $n = $base;

        DB::beginTransaction();
        try {
            if ($itemsCocina !== []) {
                $n++;
                $c = $this->crearComandaSesion($sesion, 'cocina', $itemsCocina, $n);
                if ($c) {
                    $comandasCreadas[] = $c;
                }
            }
            if ($itemsBarra !== []) {
                $n++;
                $c = $this->crearComandaSesion($sesion, 'barra', $itemsBarra, $n);
                if ($c) {
                    $comandasCreadas[] = $c;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'comandas' => $comandasCreadas,
            'primera' => $comandasCreadas[0] ?? null,
        ], 201);
    }

    public function actualizarEstado(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $comanda = Comanda::where(function ($q) use ($user) {
            $q->whereHas('sesion', fn ($sq) => $sq->where('id_empresa', $user->id_empresa))
                ->orWhereHas('pedido', fn ($pq) => $pq->where('id_empresa', $user->id_empresa));
        })->findOrFail($id);

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,preparando,listo',
        ]);

        $comanda->update($validated);
        return response()->json($comanda);
    }

    public function imprimir(int $id)
    {
        $user = auth()->user();
        $comanda = Comanda::where(function ($q) use ($user) {
            $q->whereHas('sesion', fn ($sq) => $sq->where('id_empresa', $user->id_empresa))
                ->orWhereHas('pedido', fn ($pq) => $pq->where('id_empresa', $user->id_empresa));
        })
            ->with([
                'sesion.mesa',
                'sesion.mesero',
                'pedido',
                'detalles.ordenDetalle' => fn ($q) => $q->withTrashed()->with('producto'),
                'detalles.pedidoDetalle.producto',
            ])
            ->findOrFail($id);

        $empresa = Empresa::find($user->id_empresa);

        return response()->view('restaurante.comanda-ticket', compact('comanda', 'empresa'))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
