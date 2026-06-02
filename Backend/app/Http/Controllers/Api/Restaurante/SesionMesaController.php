<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Services\Restaurante\RestauranteAutorizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SesionMesaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'mesa_id' => 'required|exists:restaurante_mesas,id',
            'num_comensales' => 'nullable|integer|min:1|max:99',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($validated['mesa_id']);

        $sesionActiva = SesionMesa::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->first();

        if ($sesionActiva) {
            return response()->json(['error' => 'La mesa ya tiene una sesión activa'], 422);
        }

        $sesion = SesionMesa::create([
            'mesa_id' => $mesa->id,
            'usuario_id' => $user->id,
            'id_empresa' => $user->id_empresa,
            'id_sucursal' => $user->id_sucursal ?? $mesa->id_sucursal,
            'num_comensales' => $validated['num_comensales'] ?? 1,
            'observaciones' => $validated['observaciones'] ?? null,
            'estado' => 'abierta',
            'opened_at' => now(),
        ]);

        $mesa->update(['estado' => 'ocupada']);

        $sesion->load(['mesa', 'mesero']);
        return response()->json($sesion, 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)
            ->with([
                'mesa',
                'mesero',
                'ordenDetalle.producto',
                'preCuentas.ordenDetalles.producto',
            ])
            ->findOrFail($id);

        return response()->json($sesion);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'num_comensales' => 'nullable|integer|min:1|max:99',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $sesion->update($validated);
        return response()->json($sesion->fresh(['mesa', 'mesero']));
    }

    public function cerrar(int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $sesion->update([
            'estado' => 'cerrada',
            'closed_at' => now(),
        ]);

        $sesion->mesa->update(['estado' => 'libre']);
        return response()->json($sesion);
    }

    /**
     * Sesiones creadas con el flujo antiguo quedaban en pre_cuenta y bloqueaban la operación.
     * Permite volver a abierta sin anular pre-cuentas ya generadas (siguen imprimibles / facturables).
     */
    public function reactivarConsumo(int $id): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($sesion->estado !== 'pre_cuenta') {
            return response()->json(['error' => 'La sesión no está en estado pre-cuenta'], 422);
        }

        $sesion->update(['estado' => 'abierta']);
        $sesion->mesa?->update(['estado' => 'ocupada']);

        $sesion->load([
            'mesa',
            'mesero',
            'ordenDetalle.producto',
            'preCuentas.ordenDetalles.producto',
        ]);

        return response()->json($sesion);
    }

    public function trasladarItems(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $authz = app(RestauranteAutorizacionService::class);
        if (! $authz->usuarioPuedeAutorizarOperaciones($user)) {
            return response()->json(['error' => 'Solo personal autorizado puede trasladar consumos entre mesas.'], 403);
        }

        $validated = $request->validate([
            'mesa_destino_id' => 'required|exists:restaurante_mesas,id',
            'orden_detalle_ids' => 'required|array|min:1',
            'orden_detalle_ids.*' => 'integer',
        ]);

        $sesionOrigen = SesionMesa::where('id_empresa', $user->id_empresa)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->findOrFail($id);

        $mesaDest = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($validated['mesa_destino_id']);
        $sesionDest = SesionMesa::where('mesa_id', $mesaDest->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->first();

        if (! $sesionDest) {
            return response()->json(['error' => 'La mesa destino no tiene sesión abierta. Abra la mesa destino antes de trasladar.'], 422);
        }
        if ($sesionDest->id === $sesionOrigen->id) {
            return response()->json(['error' => 'Seleccione otra mesa.'], 422);
        }

        try {
            DB::beginTransaction();
            foreach ($validated['orden_detalle_ids'] as $oid) {
                $item = OrdenDetalle::where('sesion_id', $sesionOrigen->id)->findOrFail($oid);
                $this->fusionarItemEnSesionDestino($item, $sesionDest);
            }
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['ok' => true]);
    }

    private function fusionarItemEnSesionDestino(OrdenDetalle $item, SesionMesa $dest): void
    {
        $notas = $item->notas;
        $q = OrdenDetalle::where('sesion_id', $dest->id)
            ->where('producto_id', $item->producto_id)
            ->whereRaw('ROUND(precio_unitario, 2) = ?', [round((float) $item->precio_unitario, 2)])
            ->where('enviado_cocina', (bool) $item->enviado_cocina)
            ->where('enviado_barra', (bool) $item->enviado_barra);
        if ($notas === null || $notas === '') {
            $q->where(function ($qq) {
                $qq->whereNull('notas')->orWhere('notas', '');
            });
        } else {
            $q->where('notas', $notas);
        }
        $existente = $q->first();
        if ($existente) {
            $existente->update([
                'cantidad' => $existente->cantidad + $item->cantidad,
            ]);
            $item->forceDelete();
        } else {
            $item->sesion_id = $dest->id;
            $item->save();
        }
    }
}
