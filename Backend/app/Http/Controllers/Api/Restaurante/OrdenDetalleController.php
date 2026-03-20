<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrdenDetalleController extends Controller
{
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

        $item = OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => $validated['cantidad'],
            'precio_unitario' => $producto->precio ?? 0,
            'notas' => $validated['notas'] ?? null,
            'enviado_cocina' => false,
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

        $item->update($validated);
        return response()->json($item->fresh('producto'));
    }

    public function destroy(int $sesionId, int $itemId): JsonResponse
    {
        $user = auth()->user();
        $sesion = SesionMesa::where('id_empresa', $user->id_empresa)->findOrFail($sesionId);
        $item = OrdenDetalle::where('sesion_id', $sesion->id)->findOrFail($itemId);

        $item->delete();
        return response()->json(null, 204);
    }
}
