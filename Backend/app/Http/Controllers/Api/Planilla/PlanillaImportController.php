<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planilla\ImportPlanillaRequest;
use App\Services\Planilla\PlanillaImportService;
use Illuminate\Support\Facades\Log;

class PlanillaImportController extends Controller
{
    protected $importService;

    public function __construct(PlanillaImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Importar planilla desde archivo Excel
     */
    public function importar(ImportPlanillaRequest $request)
    {
        try {
            $datos = $request->validated();
            $this->importService->importar($request->file('archivo'), $datos);

            return response()->json([
                'message' => 'Planilla importada exitosamente',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error('Error importando planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al importar la planilla: ' . $e->getMessage()
            ], 500);
        }
    }
}

