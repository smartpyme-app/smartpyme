<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns;

use App\Models\Admin\Empresa;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;

trait InteractsWithLibrosIva
{
    protected FacturacionElectronicaHelperService $facturacionElectronicaHelper;
    protected LibroIVAService $libroIVAService;
    protected ReporteDetalleIvaCrService $reporteDetalleIvaCrService;
    protected LibroIvaResumenFiscalService $libroIvaResumenFiscalService;

    protected function bootLibrosIva(
        FacturacionElectronicaHelperService $facturacionElectronicaHelper,
        LibroIVAService $libroIVAService,
        ReporteDetalleIvaCrService $reporteDetalleIvaCrService,
        LibroIvaResumenFiscalService $libroIvaResumenFiscalService
    ): void {
        $this->facturacionElectronicaHelper = $facturacionElectronicaHelper;
        $this->libroIVAService = $libroIVAService;
        $this->reporteDetalleIvaCrService = $reporteDetalleIvaCrService;
        $this->libroIvaResumenFiscalService = $libroIvaResumenFiscalService;
    }

    protected function obtenerEmpresa(): ?Empresa
    {
        return $this->facturacionElectronicaHelper->obtenerEmpresa();
    }

    protected function montoVentaPropioSinCuentaTerceros($venta): float
    {
        $total = (float) ($venta->total ?? 0);
        $ct = (float) ($venta->cuenta_a_terceros ?? 0);
        $neto = $total - $ct;

        return $neto > 0 ? $neto : 0.0;
    }
}
