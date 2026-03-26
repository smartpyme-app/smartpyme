<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\Mesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MesaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $query = Mesa::where('id_empresa', $user->id_empresa)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->when($request->activo !== null, fn ($q) => $q->where('activo', $request->boolean('activo')));

        $mesas = $query->with(['sesionActiva', 'reservasActivas'])->orderBy('orden')->orderBy('numero')->get();

        $mesas->each(function ($mesa) {
            $sesion = $mesa->sesionActiva;
            $reserva = $mesa->reservasActivas->first();

            if ($sesion) {
                $mesa->estado = $sesion->estado === 'pre_cuenta' ? 'pendiente_pago' : 'ocupada';
                $mesa->tiempo_abierta = $sesion->opened_at?->diffForHumans(null, true);
            } elseif ($reserva) {
                $mesa->estado = 'reservada';
            } else {
                $mesa->estado = 'libre';
            }
        });

        return response()->json($mesas);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'numero' => 'required|string|max:20',
            'capacidad' => 'nullable|integer|min:1|max:99',
            'zona' => 'nullable|string|max:50',
            'id_sucursal' => 'nullable|integer|exists:empresa_sucursales,id',
            'orden' => 'nullable|integer|min:0',
        ]);

        $validated['id_empresa'] = $user->id_empresa;
        $validated['capacidad'] = $validated['capacidad'] ?? 4;
        $validated['orden'] = $validated['orden'] ?? 0;

        $mesa = Mesa::create($validated);
        return response()->json($mesa, 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($id);
        return response()->json($mesa);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $mesa = Mesa::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'numero' => 'sometimes|string|max:20',
            'capacidad' => 'nullable|integer|min:1|max:99',
            'zona' => 'nullable|string|max:50',
            'activo' => 'sometimes|boolean',
            'orden' => 'nullable|integer|min:0',
        ]);

        $mesa->update($validated);
        return response()->json($mesa);
    }
}
