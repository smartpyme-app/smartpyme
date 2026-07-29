<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ComisionConfigController extends Controller
{
    public function __construct(
        private ComisionConfigService $configService
    ) {
    }

    public function listarCategorias(Request $request): JsonResponse
    {
        $idEmpresa = (int) $request->user()->id_empresa;
        $categorias = $this->configService->listarCategorias($idEmpresa)->values();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(1, (int) $request->input('paginate', 25)));
        $total = $categorias->count();
        $items = $categorias->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
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
