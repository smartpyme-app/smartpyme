<?php

namespace App\Http\Controllers\Api\Incentivos;

use App\Http\Controllers\Controller;
use App\Services\Incentivos\VendedorConsolidadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendedorIncentivosDashboardController extends Controller
{
    public function __construct(
        private VendedorConsolidadoService $consolidadoService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $this->validarPeriodo($request);
        $idEmpresa = (int) $request->user()->id_empresa;

        $resultado = $this->consolidadoService->listar(
            $idEmpresa,
            $validated['desde'],
            $validated['hasta']
        );

        return response()->json([
            'success' => true,
            ...$resultado,
        ]);
    }

    public function show(Request $request, int $id_vendedor): JsonResponse
    {
        $validated = $this->validarPeriodo($request);
        $idEmpresa = (int) $request->user()->id_empresa;

        return response()->json([
            'success' => true,
            'data' => $this->consolidadoService->consolidado(
                $idEmpresa,
                $id_vendedor,
                $validated['desde'],
                $validated['hasta']
            ),
        ]);
    }

    /** @return array{desde: string, hasta: string} */
    private function validarPeriodo(Request $request): array
    {
        return $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);
    }
}
