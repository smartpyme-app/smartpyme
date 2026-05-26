<?php

namespace App\Http\Controllers\Api\Contabilidad\LibrosIva;

use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\HandlesLibrosIvaSar;
use App\Http\Controllers\Api\Contabilidad\LibrosIva\Concerns\InteractsWithLibrosIva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Compras\Compra;
use App\Models\Ventas\Venta;
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\Contabilidad\FacturacionElectronicaHelperService;
use App\Services\Contabilidad\LibroIVAService;
use App\Services\Contabilidad\LibroIvaResumenFiscalService;
use App\Services\Contabilidad\LibrosIva\LibroIvaPaisResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibrosIvaHdController extends Controller
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

    private function assertHonduras(): void
    {
        if ($this->libroIvaPaisResolver->tipo() !== LibroIvaPaisResolver::TIPO_HD) {
            abort(403, 'Esta operación solo está disponible para empresas de Honduras.');
        }
    }

    public function ventas(BaseLibroIVARequest $request)
    {
        $this->assertHonduras();

        return $this->ventasSarJson($request);
    }

    public function ventasLibroExport(BaseLibroIVARequest $request)
    {
        $this->assertHonduras();

        return $this->ventasSarExcel($request);
    }

    public function compras(BaseLibroIVARequest $request)
    {
        $this->assertHonduras();

        return $this->comprasSarJson($request);
    }

    public function comprasLibroExport(BaseLibroIVARequest $request)
    {
        $this->assertHonduras();

        return $this->comprasSarExcel($request);
    }

    public function retenciones(Request $request): JsonResponse
    {
        $this->assertHonduras();
        $request->validate([
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
        ]);

        return response()->json($this->retencionesJsonHonduras($request));
    }

    private function retencionesJsonHonduras(Request $request): array
    {
        $ventas = Venta::with(['cliente'])
            ->where('estado', '!=', 'Anulada')
            ->where('iva_retenido', '>', 0)
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get()
            ->map(fn ($v) => ['registro' => $v, 'origen' => 'venta']);

        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->where('percepcion', '>', 0)
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get()
            ->map(fn ($c) => ['registro' => $c, 'origen' => 'compra']);

        $merged = $ventas->merge($compras)->sortBy(fn ($x) => $x['registro']->fecha)->values();

        return $merged->map(function (array $item) {
            $r = $item['registro'];
            $esVenta = $item['origen'] === 'venta';

            if ($esVenta) {
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
            }

            return [
                'fecha_comprobante' => $r->fecha,
                'numero_comprobante' => $r->referencia ?? '',
                'fecha_factura' => $r->fecha,
                'factura_relacionada' => $r->referencia ?? '',
                'nombre_agente_retenedor' => $r->nombre_proveedor ?? '',
                'registro_tributario_nacional' => optional($r->proveedor)->nit ?? optional($r->proveedor)->ncr ?? '',
                'importe_base_retencion' => (float) $r->sub_total,
                'impuesto_retenido' => (float) $r->percepcion,
            ];
        })->all();
    }
}
