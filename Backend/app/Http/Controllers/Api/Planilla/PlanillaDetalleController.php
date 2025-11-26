<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planilla\UpdatePlanillaDetalleRequest;
use App\Http\Resources\Planilla\PlanillaDetalleResource;
use App\Services\Planilla\PlanillaDetalleService;
use App\Services\Planilla\PlanillaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanillaDetalleController extends Controller
{
    protected $detalleService;
    protected $planillaService;

    public function __construct(
        PlanillaDetalleService $detalleService,
        PlanillaService $planillaService
    ) {
        $this->detalleService = $detalleService;
        $this->planillaService = $planillaService;
    }

    /**
     * Actualizar detalle de planilla
     */
    public function update(UpdatePlanillaDetalleRequest $request, $id)
    {
        try {
            $datos = $request->validated();
            $detalle = $this->detalleService->actualizar($id, $datos);

            // Actualizar totales de la planilla
            $this->planillaService->actualizarTotales($detalle->id_planilla);

            return response()->json([
                'message' => 'Detalle actualizado exitosamente con nuevas tablas 2025',
                'detalle' => new PlanillaDetalleResource($detalle->load('empleado')),
                'planilla' => $detalle->planilla->load('empresa')
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando detalle de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el detalle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retirar detalle de planilla
     */
    public function retirar(Request $request, $id)
    {
        try {
            $detalle = $this->detalleService->retirar($id);
            
            // Actualizar totales
            $this->planillaService->actualizarTotales($detalle->id_planilla);

            return response()->json([
                'message' => 'Detalle de planilla retirado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al retirar detalle de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al retirar detalle de planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Incluir detalle de planilla
     */
    public function incluir(Request $request, $id)
    {
        try {
            $detalle = $this->detalleService->incluir($id);
            
            // Actualizar totales
            $this->planillaService->actualizarTotales($detalle->id_planilla);

            return response()->json([
                'message' => 'Detalle de planilla incluido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al incluir detalle de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al incluir detalle de planilla: ' . $e->getMessage()
            ], 500);
        }
    }
}

