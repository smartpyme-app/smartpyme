<?php

namespace App\Http\Controllers;

use App\Exports\VentasPerdidasExport;
use App\Services\RecuperarVentasPerdidasService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VentasPerdidasController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', '2026-02-11');
        $fechaFin = $request->get('fecha_fin', '2026-02-12');

        $service = new RecuperarVentasPerdidasService($fechaInicio, $fechaFin);
        $datos = $service->getDatosCompletos();

        return view('ventas-perdidas.index', [
            'datos' => $datos,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function excel(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', '2026-02-11');
        $fechaFin = $request->get('fecha_fin', '2026-02-12');

        $export = new VentasPerdidasExport($fechaInicio, $fechaFin);
        $nombreArchivo = "ventas_perdidas_{$fechaInicio}_{$fechaFin}.xlsx";

        return Excel::download($export, $nombreArchivo);
    }
}
