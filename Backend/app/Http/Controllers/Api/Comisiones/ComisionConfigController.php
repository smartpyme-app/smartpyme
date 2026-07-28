<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ComisionConfigController extends Controller
{
    public function __construct(
        private ComisionConfigService $configService
    ) {
    }

    public function listarCategorias(Request $request): JsonResponse
    {
        $idEmpresa = (int) $request->user()->id_empresa;
        $categorias = $this->configService->listarCategorias($idEmpresa);

        return response()->json([
            'success' => true,
            'data' => $categorias,
        ]);
    }

    public function actualizarCategoria(Request $request, int $id_categoria): JsonResponse
    {
        $validated = $request->validate([
            'porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        $config = $this->configService->actualizarCategoria(
            (int) $request->user()->id_empresa,
            $id_categoria,
            (float) $validated['porcentaje']
        );

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    public function actualizarSubcategoria(Request $request, int $id_subcategoria): JsonResponse
    {
        $validated = $request->validate([
            'porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        $config = $this->configService->actualizarSubcategoria(
            (int) $request->user()->id_empresa,
            $id_subcategoria,
            (float) $validated['porcentaje']
        );

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }
}
