<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportesService;
use Illuminate\Http\Request;
use JWTAuth;

/**
 * ReportesController (Super Admin)
 *
 * Reportes financieros de la plataforma SmartPyme para el usuario Super Admin.
 */
class ReportesController extends Controller
{
    protected $reportesService;

    public function __construct(ReportesService $reportesService)
    {
        $this->reportesService = $reportesService;
    }

    /**
     * Previsión de cobros de suscripciones en el rango de fechas indicado,
     * separados por quincena (1-15 y 16-fin de mes) y por tipo de ingreso
     * (New Subscriptions = alta nueva / Renewals = renovación).
     *
     * Query params:
     *   - inicio  (string Y-m-d)  — por defecto inicio del mes en curso
     *   - fin     (string Y-m-d)  — por defecto fin del mes en curso
     */
    public function flujoEfectivo(Request $request)
    {
        JWTAuth::parseToken()->authenticate();

        $inicio = $request->input('inicio');
        $fin    = $request->input('fin');

        $datos = $this->reportesService->obtenerFlujoEfectivo($inicio, $fin);

        return response()->json($datos, 200);
    }
}
