<?php

namespace App\Http\Controllers\Api\Comisiones;

use App\Exports\Comisiones\ComisionesPorVendedorSheetsExport;
use App\Http\Controllers\Controller;
use App\Services\Comisiones\ComisionReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ComisionReporteController extends Controller
{
    public function __construct(
        private ComisionReporteService $reporteService
    ) {
    }

    public function movimientos(Request $request): JsonResponse
    {
        $movimientos = $this->reporteService->listarMovimientos(
            (int) $request->user()->id_empresa,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $movimientos->items(),
            'meta' => [
                'current_page' => $movimientos->currentPage(),
                'last_page' => $movimientos->lastPage(),
                'per_page' => $movimientos->perPage(),
                'total' => $movimientos->total(),
            ],
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse|JsonResponse
    {
        $rango = $this->reporteService->validarRangoExport($request);
        $idEmpresa = (int) $request->user()->id_empresa;

        $export = new ComisionesPorVendedorSheetsExport(
            $this->reporteService,
            $idEmpresa,
            $rango['desde'],
            $rango['hasta']
        );

        $filename = sprintf(
            'comisiones-%s-%s.xlsx',
            $rango['desde'],
            $rango['hasta']
        );

        return Excel::download($export, $filename);
    }

    public function comprobantePdf(Request $request, int $id_vendedor)
    {
        $periodoId = $this->reporteService->validarComprobante($request);
        $idEmpresa = (int) $request->user()->id_empresa;

        $datos = $this->reporteService->datosComprobante($idEmpresa, $id_vendedor, $periodoId);

        $pdf = app('dompdf.wrapper')->loadView('reportes.comisiones.comprobante', $datos);
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream('comprobante-comision-' . $id_vendedor . '.pdf');
    }
}
