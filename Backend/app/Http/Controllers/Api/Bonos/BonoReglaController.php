<?php

namespace App\Http\Controllers\Api\Bonos;

use App\Http\Controllers\Controller;
use App\Services\Bonos\BonoReglaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonoReglaController extends Controller
{
    public function __construct(
        private BonoReglaService $reglaService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $activo = $request->has('activo')
            ? filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $reglas = $this->reglaService->listar(
            (int) $request->user()->id_empresa,
            $activo
        );

        return response()->json([
            'success' => true,
            'data' => $reglas,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $regla = $this->reglaService->obtener((int) $request->user()->id_empresa, $id);

        return response()->json([
            'success' => true,
            'data' => $regla,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|string|in:meta_fija,escalonado',
            'ventana' => 'nullable|string|max:32',
            'config' => 'required|array',
            'activo' => 'nullable|boolean',
            'alcance' => 'nullable|string|in:global,vendedores',
            'id_vendedores' => 'nullable|array',
            'id_vendedores.*' => 'integer|min:1',
        ]);

        $regla = $this->reglaService->crear(
            (int) $request->user()->id_empresa,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $regla,
            'message' => 'Regla de bono creada correctamente.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'tipo' => 'sometimes|required|string|in:meta_fija,escalonado',
            'ventana' => 'nullable|string|max:32',
            'config' => 'sometimes|required|array',
            'activo' => 'nullable|boolean',
            'alcance' => 'nullable|string|in:global,vendedores',
            'id_vendedores' => 'nullable|array',
            'id_vendedores.*' => 'integer|min:1',
        ]);

        $regla = $this->reglaService->actualizar(
            (int) $request->user()->id_empresa,
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $regla,
            'message' => 'Regla de bono actualizada correctamente.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $regla = $this->reglaService->eliminar((int) $request->user()->id_empresa, $id);

        return response()->json([
            'success' => true,
            'data' => $regla,
            'message' => 'Regla de bono desactivada correctamente.',
        ]);
    }
}
