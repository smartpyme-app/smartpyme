<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\Comisiones\ComisionPeriodo;
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

    public function show(Request $request, int $id): JsonResponse
    {
        $idEmpresa = (int) $request->user()->id_empresa;
        $periodo = $this->liquidacionService->obtenerPeriodo($idEmpresa, $id);

        if ($request->boolean('estimado') && $periodo->estado === ComisionPeriodo::ESTADO_ABIERTO) {
            $periodo->setAttribute('estimado', $this->liquidacionService->previewVolumen($idEmpresa, $id, $periodo));
        }

        return response()->json([
            'success' => true,
            'data' => $periodo,
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
