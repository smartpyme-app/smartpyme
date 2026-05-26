<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\InteractsWithLibrosIva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Admin\Empresa;
use App\Exports\Contabilidad\CostaRica\ReporteDetalleIvaVentasExport;
use App\Exports\Contabilidad\CostaRica\ReporteDetalleIvaComprasExport;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LibrosIvaCrController extends Controller
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

    public function reporteDetalleIvaVentas(BaseLibroIVARequest $request): JsonResponse
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasVentas($request->inicio, $request->fin, $idSucursal);
        $totales = $this->reporteDetalleIvaCrService->totales($filas);

        return response()->json([
            'filas' => $filas,
            'totales' => $totales,
        ], 200);
    }

    public function reporteDetalleIvaCompras(BaseLibroIVARequest $request): JsonResponse
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasCompras($request->inicio, $request->fin, $idSucursal);
        $totales = $this->reporteDetalleIvaCrService->totales($filas);

        return response()->json([
            'filas' => $filas,
            'totales' => $totales,
        ], 200);
    }

    public function reporteDetalleIvaVentasExcel(BaseLibroIVARequest $request)
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasVentas($request->inicio, $request->fin, $idSucursal);

        return Excel::download(new ReporteDetalleIvaVentasExport($filas), 'Reporte_Detalle_IVA.xlsx');
    }

    public function reporteDetalleIvaVentasCsv(BaseLibroIVARequest $request)
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasVentas($request->inicio, $request->fin, $idSucursal);

        return Excel::download(new ReporteDetalleIvaVentasExport($filas), 'Reporte_Detalle_IVA.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function reporteDetalleIvaComprasExcel(BaseLibroIVARequest $request)
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasCompras($request->inicio, $request->fin, $idSucursal);

        return Excel::download(new ReporteDetalleIvaComprasExport($filas), 'Reporte_Detalle_IVA_Compras.xlsx');
    }

    public function reporteDetalleIvaComprasCsv(BaseLibroIVARequest $request)
    {
        $this->assertEmpresaCostaRica();

        $idSucursal = $request->id_sucursal ? (int) $request->id_sucursal : null;
        $filas = $this->reporteDetalleIvaCrService->filasCompras($request->inicio, $request->fin, $idSucursal);

        return Excel::download(new ReporteDetalleIvaComprasExport($filas), 'Reporte_Detalle_IVA_Compras.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    private function assertEmpresaCostaRica(): void
    {
        $empresa = Empresa::query()->find(Auth::user()->id_empresa);
        if (FacturacionElectronicaCountryResolver::codPais($empresa) !== FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            abort(403, 'Esta operación solo está disponible para empresas con país Costa Rica.');
        }
    }
}
