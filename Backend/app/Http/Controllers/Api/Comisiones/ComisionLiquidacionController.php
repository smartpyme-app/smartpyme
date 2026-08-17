<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionLiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComisionLiquidacionController extends Controller
{
    public function __construct(
        private ComisionLiquidacionService $liquidacionService
    ) {
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $liquidacion = $this->liquidacionService->marcarLiquidacionPagada(
            (int) $request->user()->id_empresa,
            $id
        );

        return response()->json([
            'success' => true,
            'data' => $liquidacion,
            'message' => 'Liquidación marcada como pagada.',
        ]);
    }
}
