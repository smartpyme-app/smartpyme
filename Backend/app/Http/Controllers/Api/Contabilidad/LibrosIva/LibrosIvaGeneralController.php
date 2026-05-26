<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\HandlesLibrosIvaSar;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\InteractsWithLibrosIva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Ventas\Venta;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use App\Services\Contabilidad\LibrosIva\LibroIvaPaisResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibrosIvaGeneralController extends Controller
{
    use InteractsWithLibrosIva;
    use HandlesLibrosIvaSar;

    public function __construct(
        FacturacionElectronicaHelperService $facturacionElectronicaHelper,
        LibroIVAService $libroIVAService,
        ReporteDetalleIvaCrService $reporteDetalleIvaCrService,
        LibroIvaResumenFiscalService $libroIvaResumenFiscalService,
        private LibroIvaPaisResolver $libroIvaPaisResolver
    ) {
        $this->bootLibrosIva($facturacionElectronicaHelper, $libroIVAService, $reporteDetalleIvaCrService, $libroIvaResumenFiscalService);
    }

    private function assertGeneral(): void
    {
        if ($this->libroIvaPaisResolver->tipo() !== LibroIvaPaisResolver::TIPO_GENERAL) {
            abort(403, 'Esta operación no aplica para el país configurado en la empresa.');
        }
    }

    public function ventas(BaseLibroIVARequest $request)
    {
        $this->assertGeneral();

        return $this->ventasSarJson($request);
    }

    public function ventasLibroExport(BaseLibroIVARequest $request)
    {
        $this->assertGeneral();

        return $this->ventasSarExcel($request);
    }

    public function compras(BaseLibroIVARequest $request)
    {
        $this->assertGeneral();

        return $this->comprasSarJson($request);
    }

    public function comprasLibroExport(BaseLibroIVARequest $request)
    {
        $this->assertGeneral();

        return $this->comprasSarExcel($request);
    }

    public function retenciones(Request $request): JsonResponse
    {
        $this->assertGeneral();
        $request->validate([
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
        ]);

        return response()->json($this->retencionesJsonVentasIvaRetenido($request));
    }

    private function retencionesJsonVentasIvaRetenido(Request $request): array
    {
        $ventas = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('iva_retenido', '>', 0)
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->orderByDesc('fecha')
            ->orderByDesc('correlativo')
            ->get();

        return $ventas->map(function ($r) {
            return [
                'fecha_comprobante' => $r->fecha,
                'numero_comprobante' => trim((string) ($r->numero_control ?? $r->correlativo ?? '')),
                'fecha_factura' => $r->fecha,
                'factura_relacionada' => trim((string) $r->correlativo),
                'nombre_agente_retenedor' => $r->nombre_cliente ?? '',
                'registro_tributario_nacional' => optional($r->cliente)->nit ?? optional($r->cliente)->ncr ?? '',
                'importe_base_retencion' => (float) $r->sub_total,
                'impuesto_retenido' => (float) $r->iva_retenido,
            ];
        })->values()->all();
    }
}
