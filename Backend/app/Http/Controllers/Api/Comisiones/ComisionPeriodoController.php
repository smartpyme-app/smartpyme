<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionLiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionPeriodoController extends Controller
{
    public function __construct(
        private ComisionLiquidacionService $liquidacionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $periodos = $this->liquidacionService->listarPeriodos(
            (int) $request->user()->id_empresa,
            $request->input('estado')
        );

        return response()->json([
            'success' => true,
            'data' => $periodos,
        ]);
    }

    public function cerrar(Request $request, int $id): JsonResponse
    {
        $periodo = $this->liquidacionService->cerrarPeriodo(
            (int) $request->user()->id_empresa,
            $id
        );

        return response()->json([
            'success' => true,
            'data' => $periodo,
            'message' => 'Período cerrado correctamente.',
        ]);
    }
}
