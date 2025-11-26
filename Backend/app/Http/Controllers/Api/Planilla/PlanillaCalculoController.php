<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planilla\ValidarCalculoRentaRequest;
use App\Services\Planilla\PlanillaCalculoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanillaCalculoController extends Controller
{
    protected $calculoService;

    public function __construct(PlanillaCalculoService $calculoService)
    {
        $this->calculoService = $calculoService;
    }

    /**
     * Recalcular renta
     */
    public function recalcularRenta(Request $request, $planillaId)
    {
        try {
            $resultado = $this->calculoService->recalcularRenta($planillaId);

            return response()->json([
                'message' => 'Recálculo de renta aplicado exitosamente',
                'tipo_recalculo' => $resultado['tipo_recalculo'],
                'empleados_afectados' => $resultado['empleados_afectados'],
                'planilla' => $resultado['planilla']
            ]);
        } catch (\Exception $e) {
            Log::error('Error en recálculo de renta: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al recalcular renta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle del cálculo de renta
     */
    public function obtenerDetalleCalculoRenta($detalleId)
    {
        try {
            $resultado = $this->calculoService->obtenerDetalleCalculoRenta($detalleId);

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Error obteniendo detalle de cálculo de renta: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el detalle del cálculo'
            ], 500);
        }
    }

    /**
     * Validar cálculo de renta
     */
    public function validarCalculoRenta(ValidarCalculoRentaRequest $request)
    {
        try {
            $validacion = $this->calculoService->validarCalculoRenta(
                $request->salario_devengado,
                $request->isss_empleado,
                $request->afp_empleado,
                $request->tipo_planilla
            );

            return response()->json([
                'validacion' => $validacion,
                'es_valido' => true,
                'mensaje' => 'Cálculo validado correctamente según decreto 2025'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en la validación: ' . $e->getMessage(),
                'es_valido' => false
            ], 400);
        }
    }
}

