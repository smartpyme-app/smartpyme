<?php

namespace App\Services\Planilla;

use App\Imports\PlanillasImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class PlanillaImportService
{
    /**
     * Importar planilla desde archivo Excel
     */
    public function importar($archivo, array $datos)
    {
        try {
            $importData = [
                'empresa_id' => auth()->user()->id_empresa,
                'sucursal_id' => auth()->user()->id_sucursal,
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'tipo_planilla' => $datos['tipo_planilla']
            ];

            Excel::import(new PlanillasImport($importData), $archivo);

            return true;
        } catch (\Exception $e) {
            Log::error('Error importando planilla: ' . $e->getMessage());
            throw $e;
        }
    }
}

