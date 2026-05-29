<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Exports\Contabilidad\LibroIvaResumenFiscalExport;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\InteractsWithLibrosIva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LibrosIvaResumenController extends Controller
{
    use InteractsWithLibrosIva;

    public function __construct(
        FacturacionElectronicaHelperService $facturacionElectronicaHelper,
        LibroIVAService $libroIVAService,
        ReporteDetalleIvaCrService $reporteDetalleIvaCrService,
        LibroIvaResumenFiscalService $libroIvaResumenFiscalService
    ) {
        $this->bootLibrosIva($facturacionElectronicaHelper, $libroIVAService, $reporteDetalleIvaCrService, $libroIvaResumenFiscalService);
    }

    public function resumenFiscal(BaseLibroIVARequest $request)
    {
        if ($request->query('formato') === 'pdf') {
            return $this->resumenFiscalPdf($request);
        }

        return response()->json($this->libroIvaResumenFiscalService->build($request), 200);
    }

    public function resumenFiscalExport(BaseLibroIVARequest $request): BinaryFileResponse
    {
        $resumen = $this->libroIvaResumenFiscalService->build($request);
        $nombreEmpresa = (string) (Auth::user()->empresa()->value('nombre') ?? 'Empresa');

        return Excel::download(
            new LibroIvaResumenFiscalExport($resumen, $nombreEmpresa),
            'Resumen-fiscal.xlsx'
        );
    }

    private function resumenFiscalPdf(BaseLibroIVARequest $request)
    {
        $resumen = $this->libroIvaResumenFiscalService->build($request);

        $pdf = app('dompdf.wrapper')->loadView('reportes.contabilidad.resumen-fiscal', [
            'resumen' => $resumen,
            'request' => $request,
        ]);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('Resumen-fiscal.pdf');
    }
}
