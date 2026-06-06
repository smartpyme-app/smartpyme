<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\ZonaRestaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZonaRestauranteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $zonas = ZonaRestaurante::where('id_empresa', $user->id_empresa)
            ->when($request->boolean('activo'), fn ($q) => $q->where('activo', true))
            ->when($request->filled('id_sucursal'), fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json($zonas);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:80',
            'id_sucursal' => 'nullable|integer',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        $validated['id_empresa'] = $user->id_empresa;
        $validated['orden'] = $validated['orden'] ?? 0;
        $validated['activo'] = $validated['activo'] ?? true;

        $zona = ZonaRestaurante::create($validated);

        return response()->json($zona, 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $zona = ZonaRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        return response()->json($zona);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $zona = ZonaRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:80',
            'id_sucursal' => 'nullable|integer',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        $zona->update($validated);

        return response()->json($zona);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $zona = ZonaRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($zona->mesas()->exists()) {
            return response()->json(['error' => 'No se puede eliminar: hay mesas asignadas a esta zona.'], 422);
        }

        $zona->delete();

        return response()->json(['ok' => true]);
    }
}
