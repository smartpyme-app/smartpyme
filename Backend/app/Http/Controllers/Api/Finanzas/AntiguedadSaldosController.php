<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Exports\AntiguedadSaldosExport;
use App\Http\Controllers\Controller;
use App\Services\Finanzas\AntiguedadSaldosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AntiguedadSaldosController extends Controller
{
    public function __construct(private AntiguedadSaldosService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->validateFilters($request);

        return response()->json($this->service->generar($request));
    }

    public function pdf(Request $request)
    {
        $this->validateFilters($request);
        $reporte = $this->service->generar($request);
        $labels = AntiguedadSaldosService::BUCKET_LABELS;

        $pdf = app('dompdf.wrapper')
            ->loadView('reportes.finanzas.antiguedad-saldos', compact('reporte', 'labels'))
            ->setPaper('letter', 'landscape');

        $nombre = 'antiguedad-saldos-' . $reporte['tipo'] . '-' . $reporte['fecha_corte'] . '.pdf';

        return $pdf->stream($nombre);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $this->validateFilters($request);
        $reporte = $this->service->generar($request);
        $filename = 'antiguedad-saldos-' . $reporte['tipo'] . '-' . $reporte['fecha_corte'] . '.xlsx';

        return Excel::download(new AntiguedadSaldosExport($reporte), $filename);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'tipo' => ['nullable', 'in:cxc,cxp'],
            'fecha_corte' => ['nullable', 'date'],
            'id_empresa' => ['nullable', 'integer'],
            'id_sucursal' => ['nullable', 'integer'],
            'id_cliente' => ['nullable', 'integer'],
            'id_proveedor' => ['nullable', 'integer'],
            'id_vendedor' => ['nullable', 'integer'],
            'buckets' => ['nullable'],
        ]);
    }
}
