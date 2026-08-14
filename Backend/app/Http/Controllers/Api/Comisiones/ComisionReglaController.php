<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionReglaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionReglaController extends Controller
{
    public function __construct(
        private ComisionReglaService $reglaService
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglasValidacion());

        $regla = $this->reglaService->crear(
            (int) $request->user()->id_empresa,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $regla,
            'message' => 'Regla de comisión creada correctamente.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate($this->reglasValidacion(true));

        $regla = $this->reglaService->actualizar(
            (int) $request->user()->id_empresa,
            $id,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $regla,
            'message' => 'Regla de comisión actualizada correctamente.',
        ]);
    }

    /** @return array<string, mixed> */
    private function reglasValidacion(bool $parcial = false): array
    {
        $req = $parcial ? 'sometimes|required' : 'required';

        return [
            'nombre' => $req.'|string|max:255',
            'tipo_calculo' => $req.'|string|in:por_categoria,por_volumen,por_margen',
            'alcance' => 'nullable|string|in:global,individual,equipo',
            'id_vendedores' => 'nullable|array',
            'id_vendedores.*' => 'integer|min:1',
            'momento_devengo' => 'nullable|string|in:al_pagar,al_facturar,por_abono',
            'reemplaza_global' => 'nullable|boolean',
            'config' => 'nullable|array',
            'salario_base' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ];
    }
}
