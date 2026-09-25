<?php

namespace App\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Controller;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\PantallaRestaurante;
use App\Services\Restaurante\PantallasComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PantallaRestauranteController extends Controller
{
    public function __construct(private PantallasComandaService $pantallas) {}

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $this->pantallas->asegurarDefecto((int) $user->id_empresa);

        $filas = PantallaRestaurante::where('id_empresa', $user->id_empresa)
            ->when($request->boolean('activo'), fn ($q) => $q->where('activo', true))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json($filas);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:80',
                Rule::unique('restaurante_pantallas', 'nombre')->where('id_empresa', $user->id_empresa),
            ],
            'orden' => 'nullable|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        $pantalla = PantallaRestaurante::create([
            'id_empresa' => $user->id_empresa,
            'nombre' => trim($validated['nombre']),
            'orden' => $validated['orden'] ?? 0,
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json($pantalla, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $pantalla = PantallaRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('restaurante_pantallas', 'nombre')
                    ->where('id_empresa', $user->id_empresa)
                    ->ignore($pantalla->id),
            ],
            'orden' => 'nullable|integer|min:0',
            'activo' => 'sometimes|boolean',
        ]);

        if (isset($validated['nombre'])) {
            $validated['nombre'] = trim($validated['nombre']);
        }
        $pantalla->update($validated);

        return response()->json($pantalla);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        $pantalla = PantallaRestaurante::where('id_empresa', $user->id_empresa)->findOrFail($id);

        if ($pantalla->productos()->exists() || Comanda::where('pantalla_id', $pantalla->id)->exists()) {
            return response()->json([
                'error' => 'No se puede eliminar: hay productos o comandas en esta pantalla.',
            ], 422);
        }

        $pantalla->delete();

        return response()->json(['ok' => true]);
    }
}
