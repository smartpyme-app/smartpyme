<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Services\Restaurante\RestauranteIdempotencyService;
use App\Services\Restaurante\RestauranteSideEffectDispatcher;
use App\Services\Restaurante\RestauranteTicketHtmlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComandaController extends Controller
{
    public function __construct(
        private RestauranteSideEffectDispatcher $sideEffects,
        private RestauranteTicketHtmlService $ticketHtml,
    ) {}

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
            'id_empresa' => (int) $sesion->id_empresa,
            'sesion_id' => $sesion->id,
            'numero_comanda' => "C-{$numeroMesa}-{$correlativo}-{$suf}",
            'estado' => 'pendiente',
            'destino' => $destino,
            'enviado_at' => now(),
        ]);

        // Bulk insert: ComandaDetalle no es AuditableModel.
        $now = now();
        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'comanda_id' => $comanda->id,
                'orden_detalle_id' => $item->id,
                'pedido_detalle_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            ComandaDetalle::insert($rows);
        }

        $comanda->load(['detalles.ordenDetalle.producto']);

        return $comanda;
    }

    /**
     * Marca enviado_* solo si aún estaba pendiente (update condicional anti-carrera).
     *
     * @param  array<int, OrdenDetalle>  $items
     */
    private function marcarItemsEnviados(array $items, string $destino): void
    {
        $ids = array_map(fn (OrdenDetalle $i) => (int) $i->id, $items);
        if ($ids === []) {
            return;
        }

        $col = $destino === 'barra' ? 'enviado_barra' : 'enviado_cocina';
        $affected = OrdenDetalle::whereIn('id', $ids)
            ->where($col, false)
            ->update([$col => true]);

        if ($affected !== count($ids)) {
            throw new \RuntimeException(
                "Conflicto al enviar comanda ({$destino}): otro proceso ya marcó uno o más ítems."
            );
        }
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        // Filtro directo por id_empresa (denormalizado); evita whereHas costoso en cocina.
        $comandas = Comanda::where('id_empresa', $user->id_empresa)
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
        return app(RestauranteIdempotencyService::class)->run(
            'enviar_comanda',
            $request,
            function () use ($id) {
                $user = auth()->user();

                try {
                    $comandasCreadas = DB::transaction(function () use ($user, $id) {
                        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
                            ->whereIn('estado', ['abierta', 'pre_cuenta'])
                            ->whereKey($id)
                            ->lockForUpdate()
                            ->with('mesa')
                            ->firstOrFail();

                        $pendientes = OrdenDetalle::where('sesion_id', $sesion->id)
                            ->with('producto')
                            ->lockForUpdate()
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
                            return [];
                        }

                        $comandasCreadas = [];
                        $n = Comanda::where('sesion_id', $sesion->id)->lockForUpdate()->count();

                        if ($itemsCocina !== []) {
                            $n++;
                            $c = $this->crearComandaSesion($sesion, 'cocina', $itemsCocina, $n);
                            if ($c) {
                                $this->marcarItemsEnviados($itemsCocina, 'cocina');
                                $comandasCreadas[] = $c;
                            }
                        }
                        if ($itemsBarra !== []) {
                            $n++;
                            $c = $this->crearComandaSesion($sesion, 'barra', $itemsBarra, $n);
                            if ($c) {
                                $this->marcarItemsEnviados($itemsBarra, 'barra');
                                $comandasCreadas[] = $c;
                            }
                        }

                        return $comandasCreadas;
                    });
                } catch (\RuntimeException $e) {
                    return response()->json(['error' => $e->getMessage()], 409);
                }

                if ($comandasCreadas === []) {
                    return response()->json(['error' => 'No hay ítems pendientes por enviar'], 422);
                }

                foreach ($comandasCreadas as $comanda) {
                    $this->sideEffects->enqueueComandaTicket((int) $comanda->id, (int) $user->id_empresa);
                }

                return response()->json([
                    'comandas' => $comandasCreadas,
                    'primera' => $comandasCreadas[0] ?? null,
                ], 201);
            }
        );
    }

    public function actualizarEstado(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $comanda = Comanda::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,preparando,listo,servido',
        ]);

        $comanda->update($validated);
        return response()->json($comanda);
    }

    public function imprimir(int $id)
    {
        $user = auth()->user();
        $html = $this->ticketHtml->rememberComandaHtml($id, (int) $user->id_empresa);

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }
}
