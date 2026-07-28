<?php

namespace App\Http\Controllers\Api\Bonos;

use App\Http\Controllers\Controller;
use App\Models\Bonos\BonoEvaluacion;
use App\Services\Bonos\BonoEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonoEvaluacionController extends Controller
{
    public function __construct(
        private BonoEvaluationService $evaluationService
    ) {
    }

    public function evaluar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periodo_inicio' => 'nullable|date',
            'periodo_fin' => 'nullable|date|after_or_equal:periodo_inicio',
        ]);

        $resumen = $this->evaluationService->evaluar(
            (int) $request->user()->id_empresa,
            $validated['periodo_inicio'] ?? null,
            $validated['periodo_fin'] ?? null,
            BonoEvaluacion::ORIGEN_MANUAL,
            (int) $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'data' => $resumen,
            'message' => 'Evaluación de bonos completada.',
        ]);
    }
}
