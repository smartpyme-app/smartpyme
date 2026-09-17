<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Exports\Contabilidad\Activos\ActivosReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Services\Contabilidad\ActivosReportesService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

class ActivosReportesController extends Controller
{
    public function __construct(
        private ActivosReportesService $reportesService
    ) {}

    public function reporte(Request $request, string $tipo)
    {
        try {
            $filtros = $this->parseFiltros($request, $tipo);
            $payload = $this->reportesService->generar($tipo, auth()->user()->id_empresa, $filtros);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($payload, 200);
    }

    public function descargar(Request $request, string $tipo, string $formato)
    {
        try {
            $filtros = $this->parseFiltros($request, $tipo);
            $payload = $this->reportesService->generar($tipo, auth()->user()->id_empresa, $filtros);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        $slug = Str::slug($payload['titulo']);

        if ($formato === 'pdf') {
            $pdf = PDF::loadView('reportes.contabilidad.activos.reporte', [
                'data' => $payload,
                'empresa' => $empresa,
            ]);
            $pdf->setPaper('letter', 'landscape');

            return $pdf->download($slug.'.pdf');
        }

        if ($formato === 'xlsx') {
            return Excel::download(new ActivosReporteExport($payload), $slug.'.xlsx');
        }

        return response()->json(['error' => 'Formato no soportado.'], 422);
    }

    private function parseFiltros(Request $request, string $tipo): array
    {
        if (! in_array($tipo, ActivosReportesService::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de reporte no válido.');
        }

        $base = $request->only(['inicio', 'fin', 'fecha_corte', 'periodo', 'id_sucursal', 'id_categoria']);

        if ($tipo === 'depreciacion-periodo' && empty($base['periodo'])) {
            $base['periodo'] = now()->format('Y-m');
        }

        if ($tipo === 'estado-activos' && empty($base['fecha_corte'])) {
            $base['fecha_corte'] = now()->toDateString();
        }

        return array_filter($base, fn ($v) => $v !== null && $v !== '');
    }
}
