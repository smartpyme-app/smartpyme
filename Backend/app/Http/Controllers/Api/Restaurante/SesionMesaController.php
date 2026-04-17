<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
}
