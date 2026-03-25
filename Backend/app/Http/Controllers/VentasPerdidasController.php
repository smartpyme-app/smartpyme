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
        $idEmpresa = $request->get('id_empresa') ? (int) $request->get('id_empresa') : null;

        $service = new RecuperarVentasPerdidasService($fechaInicio, $fechaFin, $idEmpresa);
        $datos = $service->getDatosCompletos();

        return view('ventas-perdidas.index', [
            'datos' => $datos,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'idEmpresa' => $idEmpresa,
        ]);
    }

    public function excel(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', '2026-02-11');
        $fechaFin = $request->get('fecha_fin', '2026-02-12');
        $idEmpresa = $request->get('id_empresa') ? (int) $request->get('id_empresa') : null;

        $export = new VentasPerdidasExport($fechaInicio, $fechaFin, $idEmpresa);
        $nombreArchivo = "ventas_perdidas_{$fechaInicio}_{$fechaFin}";
        if ($idEmpresa) {
            $nombreArchivo .= "_empresa_{$idEmpresa}";
        }
        $nombreArchivo .= ".xlsx";

        return Excel::download($export, $nombreArchivo);
    }
}
