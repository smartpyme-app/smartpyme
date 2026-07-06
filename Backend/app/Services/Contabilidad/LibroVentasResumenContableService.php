<?php

namespace App\Services\Contabilidad;

use App\Exports\Contabilidad\Honduras\LibroVentasExport as LibroVentasHondurasExport;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

/**
 * Cuadro resumen de ventas por categoría fiscal (libro de ventas / análisis mensual).
 */
final class LibroVentasResumenContableService
{
    public function __construct(
        private FacturacionElectronicaHelperService $feHelper
    ) {}

    /**
     * @return array{filas: array<int, array{
     *   descripcion: string,
     *   valor_neto: float,
     *   debito_fiscal: float,
     *   iva_retenido: float,
     *   total_ventas: float,
     *   tipo: string
     * }>}
     */
    public function build(BaseLibroIVARequest $request): array
    {
        $empresa = $this->feHelper->obtenerEmpresa();
        $pais = optional($empresa)->pais ?? '';
        $codPais = $empresa
            ? FacturacionElectronicaCountryResolver::codPais($empresa)
            : FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;

        if ($pais === 'El Salvador') {
            return $this->buildElSalvador($request);
        }

        if ($pais === 'Honduras') {
            return $this->buildHonduras($request);
        }

        if ($codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return $this->buildDesdeMovimientos($request, 'VENTAS NO SUJETAS');
        }

        return $this->buildDesdeMovimientos($request, 'VENTAS NO SUJETAS');
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>}
     */
    private function buildElSalvador(BaseLibroIVARequest $request): array
    {
        $contrib = $this->vacios();
        $consumidor = $this->vacios();
        $exportaciones = 0.0;
        $terceros = 0.0;

        $ventasCf = Venta::with(['documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', fn ($q) => $q->where('nombre', 'Crédito fiscal'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $ventasCf = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventasCf);

        foreach ($ventasCf as $venta) {
            $this->acumular($contrib, $venta, 1.0);
            $terceros += (float) ($venta->cuenta_a_terceros ?? 0);
        }

        $devoluciones = $this->devolucionesPeriodo($request);

        foreach ($devoluciones as $devolucion) {
            $venta = $devolucion->venta;
            if (! $venta || strtolower(trim(optional($venta->documento)->nombre ?? '')) !== 'crédito fiscal') {
                continue;
            }
            $this->acumularDevolucion($contrib, $devolucion);
        }

        $ventasCons = Venta::with(['documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function ($q) {
                $q->where('nombre', 'Factura')
                    ->orWhere('nombre', 'Factura de exportación');
            })
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $ventasCons = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventasCons);

        foreach ($ventasCons as $venta) {
            $doc = strtolower(trim(optional($venta->documento)->nombre ?? ''));
            if ($doc === 'factura de exportación') {
                $exportaciones += $this->montoPropioSinTerceros($venta);

                continue;
            }
            $this->acumular($consumidor, $venta, 1.0);
            $terceros += (float) ($venta->cuenta_a_terceros ?? 0);
        }

        $contrib = $this->redondearBucket($contrib);
        $consumidor = $this->redondearBucket($consumidor);
        $exportaciones = round($exportaciones, 2);
        $terceros = round($terceros, 2);

        $gravCf = $this->fila(
            'VENTAS NETAS GRAVADAS SEGUN COMPROBANTES DE CREDITO FISCAL',
            $contrib['gravadas'],
            $contrib['debito'],
            $contrib['iva_retenido'],
            'detalle'
        );
        $gravFact = $this->fila(
            'VENTAS NETAS GRAVADAS SEGUN FACTURAS',
            $consumidor['gravadas'],
            $consumidor['debito'],
            $consumidor['iva_retenido'],
            'detalle'
        );
        $subGrav = $this->filaSubtotal('TOTAL VENTAS INTERNAS GRAVADAS', [$gravCf, $gravFact]);

        $exContrib = $this->fila('VENTAS INTERNAS NETAS EXENTAS A CONTRIBUYENTES', $contrib['exentas'], 0, 0, 'detalle');
        $exCons = $this->fila('VENTAS INTERNAS NETAS EXENTAS A CONSUMIDORES', $consumidor['exentas'], 0, 0, 'detalle');
        $subEx = $this->filaSubtotal('TOTAL OPERACIONES INTERNAS EXENTAS', [$exContrib, $exCons]);

        $nsContrib = $this->fila('VENTAS NO SUJETAS A CONTRIBUYENTES', $contrib['no_sujetas'], 0, 0, 'detalle');
        $nsCons = $this->fila('VENTAS NO SUJETAS A CONSUMIDORES', $consumidor['no_sujetas'], 0, 0, 'detalle');
        $subNs = $this->filaSubtotal('TOTAL VENTAS NO SUJETAS', [$nsContrib, $nsCons]);

        $filas = [$gravCf, $gravFact, $subGrav, $exContrib, $exCons, $subEx, $nsContrib, $nsCons, $subNs];

        $tercerosFila = null;
        if (abs($terceros) > 0.00001) {
            $tercerosFila = $this->fila('VENTAS A CUENTA DE TERCEROS', $terceros, 0, 0, 'detalle');
            $filas[] = $tercerosFila;
        }

        $expFila = $this->fila('EXPORTACIONES SEGUN FACTURAS DE EXPORTACION', $exportaciones, 0, 0, 'detalle');
        $filas[] = $expFila;

        $detalleRows = array_values(array_filter([
            $gravCf, $gravFact, $exContrib, $exCons, $nsContrib, $nsCons, $tercerosFila, $expFila,
        ]));
        $filas[] = $this->filaSubtotal('TOTALES', $detalleRows, 'total');

        return ['filas' => $filas];
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>}
     */
    private function buildHonduras(BaseLibroIVARequest $request): array
    {
        $exp = new LibroVentasHondurasExport();
        $exp->filter($request);
        $filasLibro = collect($exp->rowsForApi());

        $gravadas = round((float) $filasLibro->sum('importe_gravada'), 2);
        $exentas = round((float) $filasLibro->sum('importe_exenta'), 2);
        $exoneradas = round((float) $filasLibro->sum('importe_exonerada'), 2);
        $debito = round((float) $filasLibro->sum('impuesto_ventas'), 2);
        $exportaciones = round((float) $filasLibro->sum('importe_exportacion'), 2);
        $retenido = round((float) $this->sumIvaRetenidoPeriodo($request), 2);

        $fGrav = $this->fila('VENTAS GRAVADAS', $gravadas, $debito, $retenido, 'detalle');
        $fEx = $this->fila('VENTAS EXENTAS', $exentas, 0, 0, 'detalle');
        $fExon = $this->fila('VENTAS EXONERADAS', $exoneradas, 0, 0, 'detalle');
        $subInt = $this->filaSubtotal('TOTAL VENTAS INTERNAS', [$fGrav, $fEx, $fExon]);
        $fExp = $this->fila('EXPORTACIONES', $exportaciones, 0, 0, 'detalle');

        $detalle = [$fGrav, $fEx, $fExon, $fExp];
        $filas = [$fGrav, $fEx, $fExon, $subInt, $fExp, $this->filaSubtotal('TOTALES', $detalle, 'total')];

        return ['filas' => $filas];
    }

    /**
     * Resumen unificado para CR, GT y demás países (misma lógica que libro ventas genérico).
     *
     * @return array{filas: array<int, array<string, mixed>>}
     */
    private function buildDesdeMovimientos(BaseLibroIVARequest $request, string $etiquetaNoSujetas): array
    {
        $bucket = $this->vacios();
        $exportaciones = 0.0;
        $terceros = 0.0;

        $ventas = Venta::with(['documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        foreach ($ventas as $venta) {
            if ($this->esExportacion($venta)) {
                $exportaciones += (float) ($venta->total ?? 0);

                continue;
            }
            $this->acumular($bucket, $venta, 1.0);
            $terceros += (float) ($venta->cuenta_a_terceros ?? 0);
        }

        foreach ($this->devolucionesPeriodo($request) as $devolucion) {
            if ($devolucion->venta && $this->esExportacion($devolucion->venta)) {
                $total = (float) ($devolucion->total ?? 0);
                $exportaciones += $total > 0 ? -$total : $total;

                continue;
            }
            $this->acumularDevolucion($bucket, $devolucion);
        }

        $bucket = $this->redondearBucket($bucket);
        $exportaciones = round($exportaciones, 2);
        $terceros = round($terceros, 2);

        $fGrav = $this->fila('VENTAS GRAVADAS', $bucket['gravadas'], $bucket['debito'], $bucket['iva_retenido'], 'detalle');
        $fEx = $this->fila('VENTAS EXENTAS', $bucket['exentas'], 0, 0, 'detalle');
        $fNs = $this->fila($etiquetaNoSujetas, $bucket['no_sujetas'], 0, 0, 'detalle');
        $subInt = $this->filaSubtotal('TOTAL VENTAS INTERNAS', [$fGrav, $fEx, $fNs]);

        $filas = [$fGrav, $fEx, $fNs, $subInt];

        $tercerosFila = null;
        if (abs($terceros) > 0.00001) {
            $tercerosFila = $this->fila('VENTAS A CUENTA DE TERCEROS', $terceros, 0, 0, 'detalle');
            $filas[] = $tercerosFila;
        }

        $fExp = $this->fila('EXPORTACIONES', $exportaciones, 0, 0, 'detalle');
        $filas[] = $fExp;

        $detalle = array_values(array_filter([$fGrav, $fEx, $fNs, $tercerosFila, $fExp]));
        $filas[] = $this->filaSubtotal('TOTALES', $detalle, 'total');

        return ['filas' => $filas];
    }

    private function sumIvaRetenidoPeriodo(BaseLibroIVARequest $request): float
    {
        $ventas = (float) Venta::query()
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->sum('iva_retenido');

        $devoluciones = (float) DevolucionVenta::query()
            ->where('enable', true)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->sum('iva_retenido');

        return round($ventas - $devoluciones, 2);
    }

    /**
     * @return \Illuminate\Support\Collection<int, DevolucionVenta>
     */
    private function devolucionesPeriodo(BaseLibroIVARequest $request)
    {
        return DevolucionVenta::with(['venta.documento'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();
    }

    private function esExportacion(Venta $venta): bool
    {
        $doc = strtolower(trim(optional($venta->documento)->nombre ?? ''));

        return str_contains($doc, 'exportación') || str_contains($doc, 'exportacion');
    }

    /**
     * @return array{gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}
     */
    private function vacios(): array
    {
        return [
            'gravadas' => 0.0,
            'exentas' => 0.0,
            'no_sujetas' => 0.0,
            'debito' => 0.0,
            'iva_retenido' => 0.0,
        ];
    }

    /**
     * @param  array{gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}  $bucket
     */
    private function acumular(array &$bucket, Venta $venta, float $signo): void
    {
        $bucket['gravadas'] += $signo * LibroIvaMontosHelper::ventasGravadas($venta);
        $bucket['exentas'] += $signo * LibroIvaMontosHelper::ventasExentas($venta);
        $bucket['no_sujetas'] += $signo * LibroIvaMontosHelper::ventasNoSujetas($venta);
        $bucket['debito'] += $signo * (float) ($venta->iva ?? 0);
        $bucket['iva_retenido'] += $signo * (float) ($venta->iva_retenido ?? 0);
    }

    /**
     * @param  array{gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}  $bucket
     */
    private function acumularDevolucion(array &$bucket, DevolucionVenta $devolucion): void
    {
        $mult = fn (float $v) => $v > 0 ? -$v : $v;

        $bucket['gravadas'] += $mult(LibroIvaMontosHelper::ventasGravadas($devolucion));
        $bucket['exentas'] += $mult(LibroIvaMontosHelper::ventasExentas($devolucion));
        $bucket['no_sujetas'] += $mult(LibroIvaMontosHelper::ventasNoSujetas($devolucion));
        $bucket['debito'] += $mult((float) ($devolucion->iva ?? 0));
        $ivaRet = (float) ($devolucion->iva_retenido ?? 0);
        $bucket['iva_retenido'] += $ivaRet > 0 ? -$ivaRet : $ivaRet;
    }

    /**
     * @param  array{gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}  $bucket
     * @return array{gravadas: float, exentas: float, no_sujetas: float, debito: float, iva_retenido: float}
     */
    private function redondearBucket(array $bucket): array
    {
        foreach ($bucket as $k => $v) {
            $bucket[$k] = round($v, 2);
        }

        return $bucket;
    }

    private function montoPropioSinTerceros(Venta $venta): float
    {
        $total = (float) ($venta->total ?? 0);
        $ct = (float) ($venta->cuenta_a_terceros ?? 0);
        $neto = $total - $ct;

        return $neto > 0 ? $neto : 0.0;
    }

    /**
     * @param  array<int, array{valor_neto: float, debito_fiscal: float, iva_retenido: float, total_ventas: float}>  $filas
     * @return array{descripcion: string, valor_neto: float, debito_fiscal: float, iva_retenido: float, total_ventas: float, tipo: string}
     */
    private function filaSubtotal(string $descripcion, array $filas, string $tipo = 'subtotal'): array
    {
        $neto = 0.0;
        $debito = 0.0;
        $retenido = 0.0;
        $total = 0.0;
        foreach ($filas as $f) {
            $neto += (float) ($f['valor_neto'] ?? 0);
            $debito += (float) ($f['debito_fiscal'] ?? 0);
            $retenido += (float) ($f['iva_retenido'] ?? 0);
            $total += (float) ($f['total_ventas'] ?? 0);
        }

        return [
            'descripcion' => $descripcion,
            'valor_neto' => round($neto, 2),
            'debito_fiscal' => round($debito, 2),
            'iva_retenido' => round($retenido, 2),
            'total_ventas' => round($total, 2),
            'tipo' => $tipo,
        ];
    }

    private function fila(
        string $descripcion,
        float $valorNeto,
        float $debitoFiscal,
        float $ivaRetenido,
        string $tipo
    ): array {
        return [
            'descripcion' => $descripcion,
            'valor_neto' => round($valorNeto, 2),
            'debito_fiscal' => round($debitoFiscal, 2),
            'iva_retenido' => round($ivaRetenido, 2),
            'total_ventas' => $this->totalVentasFila($valorNeto, $debitoFiscal, $ivaRetenido),
            'tipo' => $tipo,
        ];
    }

    private function totalVentasFila(float $neto, float $debito, float $retenido): float
    {
        if ($retenido > 0.00001) {
            return round($neto + $debito - $retenido, 2);
        }
        if ($debito > 0.00001) {
            return round($neto + $debito, 2);
        }

        return round($neto, 2);
    }
}
