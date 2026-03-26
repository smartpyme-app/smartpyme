<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ComandaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $comandas = Comanda::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->whereIn('estado', ['pendiente', 'preparando', 'listo'])
            ->with(['sesion.mesa', 'detalles.ordenDetalle.producto'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comandas);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->findOrFail($id);

        $itemsPendientes = OrdenDetalle::where('sesion_id', $sesion->id)
            ->where('enviado_cocina', false)
            ->whereHas('producto', fn ($q) => $q->where('genera_comanda', true))
            ->get();

        if ($itemsPendientes->isEmpty()) {
            return response()->json(['error' => 'No hay ítems pendientes para enviar a cocina'], 422);
        }

        $numeroComanda = Comanda::where('sesion_id', $sesion->id)->count() + 1;

        DB::beginTransaction();
        try {
            $comanda = Comanda::create([
                'sesion_id' => $sesion->id,
                'numero_comanda' => "C-{$sesion->mesa->numero}-{$numeroComanda}",
                'estado' => 'pendiente',
                'enviado_at' => now(),
            ]);

            foreach ($itemsPendientes as $item) {
                ComandaDetalle::create([
                    'comanda_id' => $comanda->id,
                    'orden_detalle_id' => $item->id,
                ]);
                $item->update(['enviado_cocina' => true]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $comanda->load(['detalles.ordenDetalle.producto']);
        return response()->json($comanda, 201);
    }

    public function actualizarEstado(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $comanda = Comanda::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->findOrFail($id);

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,preparando,listo',
        ]);

        $comanda->update($validated);
        return response()->json($comanda);
    }

    public function imprimir(int $id)
    {
        $user = auth()->user();
        $comanda = Comanda::whereHas('sesion', fn ($q) => $q->where('id_empresa', $user->id_empresa))
            ->with(['sesion.mesa', 'sesion.mesero', 'detalles.ordenDetalle.producto'])
            ->findOrFail($id);

        $empresa = Empresa::find($user->id_empresa);

        return response()->view('restaurante.comanda-ticket', compact('comanda', 'empresa'))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
