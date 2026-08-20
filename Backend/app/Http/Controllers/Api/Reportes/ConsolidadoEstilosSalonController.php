<?php

namespace App\Http\Controllers\Api\Reportes;

use App\Http\Controllers\Controller;
use App\Services\EstilosSalon\ConsolidadoEstilosSalonService;
use App\Support\EstilosSalon\EstilosSalonPeriodo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsolidadoEstilosSalonController extends Controller
{
    public function __construct(private ConsolidadoEstilosSalonService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $idEmpresa = (int) $request->user()->id_empresa;
        $disponible = EstilosSalonPeriodo::empresaPermitida($idEmpresa);
        [$fechaInicio, $fechaFin] = EstilosSalonPeriodo::rangoSugerido(Carbon::today());

        return response()->json([
            'disponible' => $disponible,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);
    }

    public function excel(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! EstilosSalonPeriodo::empresaPermitida((int) $request->user()->id_empresa)) {
            return response()->json(['error' => 'Este reporte solo está disponible para Estilo\'s Salón.'], 403);
        }

        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $empresas = $this->service->empresasParaExport();

        if ($empresas === []) {
            return response()->json(['error' => 'No hay empresas válidas para generar el reporte.'], 422);
        }

        $filename = "ventas-por-categoria-sucursal-{$datos['fecha_inicio']}-{$datos['fecha_fin']}.xlsx";

        return Excel::download(
            $this->service->makeExport($datos['fecha_inicio'], $datos['fecha_fin'], $empresas),
            $filename
        );
    }
}
