<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Services\Planilla\PlanillaExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanillaExportController extends Controller
{
    protected $exportService;

    public function __construct(PlanillaExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Exportar planilla a Excel
     */
    public function exportExcel($id)
    {
        try {
            return $this->exportService->exportarExcel($id);
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a Excel: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar planilla a PDF
     */
    public function exportPDF($id)
    {
        try {
            return $this->exportService->exportarPDF($id);
        } catch (\Exception $e) {
            Log::error('Error exportando planilla a PDF: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar a PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar boletas de pago
     */
    public function generarBoletas($id)
    {
        try {
            return $this->exportService->generarBoletas($id);
        } catch (\Exception $e) {
            Log::error('Error generando boletas: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar las boletas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar boleta individual
     */
    public function generarBoletaIndividual($id_detalle)
    {
        try {
            return $this->exportService->generarBoletaIndividual($id_detalle);
        } catch (\Exception $e) {
            Log::error('Error generando boleta individual: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al generar la boleta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener descuentos patronales
     */
    public function obtenerDescuentosPatronales($id)
    {
        try {
            $resultado = $this->exportService->obtenerDescuentosPatronales($id);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Error obteniendo descuentos patronales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener descuentos patronales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar detalles de planilla
     */
    public function exportarDetallesPlanilla(Request $request)
    {
        try {
            $filtros = $request->only(['vista', 'buscador', 'id_departamento', 'id_cargo', 'estado']);
            return $this->exportService->exportarDetalles($request->input('id_planilla'), $filtros);
        } catch (\Exception $e) {
            Log::error('Error exportando detalles de planilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar plantilla de importación
     */
    public function descargarPlantilla()
    {
        try {
            return $this->exportService->descargarPlantilla();
        } catch (\Exception $e) {
            Log::error('Error descargando plantilla: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al descargar la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }
}

