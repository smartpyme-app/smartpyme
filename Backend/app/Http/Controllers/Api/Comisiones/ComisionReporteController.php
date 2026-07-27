<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionReporteController extends Controller
{
    public function __construct(
        private ComisionReporteService $reporteService
    ) {
    }

    public function movimientos(Request $request): JsonResponse
    {
        $movimientos = $this->reporteService->listarMovimientos(
            (int) $request->user()->id_empresa,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $movimientos->items(),
            'meta' => [
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'per_page' => $movimientos->perPage(),
                'total' => $movimientos->total(),
            ],
        ]);
    }

    public function exportExcel(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Exportación Excel pendiente de implementación (Task 7).',
        ], 501);
    }

    public function comprobantePdf(Request $request, int $id_vendedor): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Comprobante PDF pendiente de implementación (Task 7).',
        ], 501);
    }
}
