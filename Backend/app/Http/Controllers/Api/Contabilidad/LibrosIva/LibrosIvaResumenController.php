<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\InteractsWithLibrosIva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use Illuminate\Http\JsonResponse;

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

    public function resumenFiscal(BaseLibroIVARequest $request): JsonResponse
    {
        return response()->json($this->libroIvaResumenFiscalService->build($request), 200);
    }
}
