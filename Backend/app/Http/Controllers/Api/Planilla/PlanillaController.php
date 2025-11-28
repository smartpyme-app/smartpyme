<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planilla\StorePlanillaRequest;
use App\Http\Requests\Planilla\UpdatePlanillaRequest;
use App\Http\Resources\Planilla\PlanillaResource;
use App\Http\Resources\Planilla\PlanillaResumenResource;
use App\Services\Planilla\PlanillaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanillaController extends Controller
{
    protected $planillaService;

    public function __construct(PlanillaService $planillaService)
    {
        $this->planillaService = $planillaService;
    }

    /**
     * Listar planillas
     */
    public function index(Request $request)
    {
        try {
            $filtros = $request->only(['anio', 'mes', 'estado', 'tipo_planilla', 'buscador']);
            $query = $this->planillaService->listar($filtros);
            
            $paginate = $request->get('paginate', 10);
            $planillas = $query->paginate($paginate);

            return PlanillaResumenResource::collection($planillas);
        } catch (\Exception $e) {
            Log::error('Error listando planillas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al listar las planillas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nueva planilla
     */
    public function store(StorePlanillaRequest $request)
    {
        try {
            $datos = $request->validated();
            $resultado = $this->planillaService->crear($datos);

            return response()->json([
                'message' => 'Planilla generada exitosamente',
                'planilla' => new PlanillaResource($resultado['planilla']),
                'estadisticas' => $resultado['estadisticas']
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error generando planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una planilla
     */
    public function show(Request $request)
    {
        try {
            $filtros = $request->only(['buscador', 'id_departamento', 'id_cargo', 'paginate']);
            $filtros['paginate'] = $request->get('paginate', 10);
            
            $resultado = $this->planillaService->obtenerDetalles($request->id, $filtros);

            $planillaArray = [
                'id' => $resultado['planilla']->id,
                'empresa' => [
                    'id' => $resultado['planilla']->empresa->id,
                    'nombre' => $resultado['planilla']->empresa->nombre,
                    'cod_pais' => $resultado['planilla']->empresa->cod_pais,
                ],
                'detalles' => $resultado['detalles'],
                'total_salarios' => $resultado['planilla']->total_salarios,
                'total_deducciones' => $resultado['planilla']->total_deducciones,
                'total_neto' => $resultado['planilla']->total_neto,
                'estado' => $resultado['planilla']->estado,
                'totales' => $resultado['totales'],
                'tipo_planilla' => $resultado['planilla']->tipo_planilla
            ];

            return response()->json($planillaArray);
        } catch (\Exception $e) {
            Log::error('Error en show de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar planilla
     */
    public function update(UpdatePlanillaRequest $request, $id)
    {
        try {
            $datos = $request->validated();
            $planilla = $this->planillaService->actualizar($id, $datos);

            return response()->json([
                'message' => 'Planilla actualizada exitosamente',
                'planilla' => new PlanillaResource($planilla)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar planilla
     */
    public function destroy($id)
    {
        try {
            $this->planillaService->eliminar($id);

            return response()->json([
                'message' => 'Planilla eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al eliminar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }
}

