<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Services\Planilla\PlanillaAprobacionService;
use Illuminate\Support\Facades\Log;

class PlanillaAprobacionController extends Controller
{
    protected $aprobacionService;

    public function __construct(PlanillaAprobacionService $aprobacionService)
    {
        $this->aprobacionService = $aprobacionService;
    }

    /**
     * Aprobar planilla
     */
    public function approve($id)
    {
        try {
            $resultado = $this->aprobacionService->aprobar($id);

            return response()->json([
                'message' => 'Planilla aprobada exitosamente',
                'detalles_actualizados' => $resultado['detalles_actualizados']
            ]);
        } catch (\Exception $e) {
            Log::error('Error al aprobar la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al aprobar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revertir planilla
     */
    public function revert($id)
    {
        try {
            $resultado = $this->aprobacionService->revertir($id);

            return response()->json([
                'message' => 'Planilla revertida exitosamente',
                'detalles_actualizados' => $resultado['detalles_actualizados']
            ]);
        } catch (\Exception $e) {
            Log::error('Error al revertir la planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al revertir la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar pago de planilla
     */
    public function processPayment($id)
    {
        try {
            $resultado = $this->aprobacionService->procesarPago($id);

            return response()->json([
                'message' => "Pago procesado exitosamente. Correos enviados: {$resultado['emails_enviados']}",
                'emails_enviados' => $resultado['emails_enviados'],
                'detalles_procesados' => $resultado['detalles_procesados'],
                'empleados_sin_email' => $resultado['empleados_sin_email'],
                'empleados_inactivos' => $resultado['empleados_inactivos'],
                'errores' => $resultado['errores'],
                'estadisticas' => $resultado['estadisticas']
            ]);
        } catch (\Exception $e) {
            Log::error('Error al procesar pago de planilla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al procesar el pago: ' . $e->getMessage()
            ], 500);
        }
    }
}

