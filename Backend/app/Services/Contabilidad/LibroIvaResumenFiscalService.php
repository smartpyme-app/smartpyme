<?php

namespace App\Services\Contabilidad;

use App\Exports\Contabilidad\Honduras\LibroComprasExport as LibroComprasHondurasExport;
use App\Exports\Contabilidad\Honduras\LibroVentasExport as LibroVentasHondurasExport;
use App\Http\Requests\Contabilidad\LibrosIVA\BaseLibroIVARequest;
use App\Models\Compras\Compra;
use App\Models\Compras\Devoluciones\Devolucion as DevolucionCompra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resumen fiscal unificado (ventas, compras, gastos, IVA, desglose por tarifa cuando aplica).
 */
final class LibroIvaResumenFiscalService
{
    private FacturacionElectronicaHelperService $feHelper;

    public function __construct(
        FacturacionElectronicaHelperService $feHelper
    ) {
        $this->feHelper = $feHelper;
    }

    public function build(BaseLibroIVARequest $request): array
    {
        $empresa = $this->feHelper->obtenerEmpresa();
        $pais = optional($empresa)->pais ?? '';
        $codPais = $empresa ? FacturacionElectronicaCountryResolver::codPais($empresa) : FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;

        if ($codPais === FacturacionElectronicaCountryResolver::CODIGO_COSTA_RICA) {
            return $this->buildCostaRica($request, $pais);
        }

        if ($pais === 'El Salvador') {
            return $this->buildElSalvador($request, $pais);
        }

        return $this->buildHondurasYOtros($request, $pais);
    }

    private function buildCostaRica(BaseLibroIVARequest $request, string $paisNombre): array
    {
        $ventas = $this->ventasLibroCr($request);
        $devoluciones = $this->devolucionesLibroCr($request);
        $ctxCompras = $this->contextoLibrosComprasCr($request);

        $totalVentas = round(
            (float) $ventas->sum(fn (Venta $v) => (float) $v->total)
            + (float) $devoluciones->sum(function (DevolucionVenta $d) {
                $total = (float) $d->total;

                return $total > 0 ? -$total : $total;
            }),
            2
        );

        $ivaDebito = round(
            (float) $ventas->sum(fn (Venta $v) => (float) $v->iva)
            - (float) $ventas->sum(fn (Venta $v) => (float) ($v->iva_devuelto ?? 0))
            + (float) $devoluciones->sum(function (DevolucionVenta $d) {
                $iva = (float) $d->iva;

                return $iva > 0 ? -$iva : $iva;
            }),
            2
        );

        $comprasLibro = $this->comprasLibroFiscal($request);

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $totalVentas,
                'compras' => $ctxCompras['total_compras'],
                'compras_sin_devoluciones' => round((float) $comprasLibro->sum(fn (Compra $c) => (float) $c->total), 2),
                'gastos' => $ctxCompras['total_gastos'],
            ],
            'ventas_por_impuesto' => $this->ventasPorImpuestoDesdeVentaImpuestos(
                $ventas,
                $devoluciones
            ),
            'compras_por_impuesto' => $this->comprasPorImpuestoDesdeCompraImpuestos($comprasLibro),
            'iva' => [
                'iva_a_favor' => $ctxCompras['iva_credito'],
                'credito_fiscal_compras' => $ctxCompras['credito_compras'],
                'credito_fiscal_gastos' => $ctxCompras['credito_gastos'],
                'credito_fiscal_devoluciones_compras' => $ctxCompras['credito_devoluciones'],
                'iva_en_contra' => $ivaDebito,
                'diferencia_estimada_pago_iva' => round($ivaDebito - $ctxCompras['iva_credito'], 2),
            ],
            'pago_a_cuenta_iva' => [
                'aplica' => false,
                'monto' => null,
                'descripcion' => null,
            ],
        ];
    }

    /**
     * Ventas del periodo (tabla ventas + venta_impuestos; sin leer DTE en S3).
     *
     * @return Collection<int, Venta>
     */
    private function ventasLibroCr(BaseLibroIVARequest $request): Collection
    {
        return Venta::query()
            ->with(['impuestos.impuesto', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, DevolucionVenta>
     */
    private function devolucionesLibroCr(BaseLibroIVARequest $request): Collection
    {
        return DevolucionVenta::query()
            ->with(['venta.impuestos.impuesto'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Totales de compras, gastos e IVA crédito desde tablas (sin DTE/S3).
     *
     * @return array{
     *   total_compras: float,
     *   total_gastos: float,
     *   credito_compras: float,
     *   credito_gastos: float,
     *   credito_devoluciones: float,
     *   iva_credito: float
     * }
     */
    private function contextoLibrosComprasCr(BaseLibroIVARequest $request): array
    {
        $compras = Compra::query()
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $gastos = Gasto::query()
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $devoluciones = DevolucionCompra::query()
            ->where('enable', true)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $creditoCompras = round((float) $compras->sum(fn (Compra $c) => (float) $c->iva), 2);
        $creditoGastos = round((float) $gastos->sum(fn (Gasto $g) => (float) $g->iva), 2);
        $creditoDevoluciones = round((float) $devoluciones->sum(function (DevolucionCompra $d) {
            $iva = (float) $d->iva;

            return $iva > 0 ? -$iva : $iva;
        }), 2);

        $totalCompras = round(
            (float) $compras->sum(fn (Compra $c) => (float) $c->total)
            + (float) $devoluciones->sum(function (DevolucionCompra $d) {
                $total = (float) $d->total;

                return $total > 0 ? -$total : $total;
            }),
            2
        );

        $totalGastos = round((float) $gastos->sum(fn (Gasto $g) => (float) $g->total), 2);
        $ivaCredito = round($creditoCompras + $creditoGastos + $creditoDevoluciones, 2);

        return [
            'total_compras' => $totalCompras,
            'total_gastos' => $totalGastos,
            'credito_compras' => $creditoCompras,
            'credito_gastos' => $creditoGastos,
            'credito_devoluciones' => $creditoDevoluciones,
            'iva_credito' => $ivaCredito,
        ];
    }

    /**
     * Ventas del libro Honduras (misma consulta que LibroVentasExport).
     *
     * @return Collection<int, Venta>
     */
    private function ventasLibroHonduras(BaseLibroIVARequest $request): Collection
    {
        return Venta::query()
            ->with(['impuestos.impuesto', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('correlativo')
            ->get();
    }

    /**
     * @return Collection<int, DevolucionVenta>
     */
    private function devolucionesLibroHonduras(BaseLibroIVARequest $request): Collection
    {
        return DevolucionVenta::query()
            ->with(['venta.impuestos.impuesto'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Totales y ventas según libros contribuyentes + consumidor final (El Salvador).
     *
     * @return array{
     *   ventas: Collection<int, Venta>,
     *   devoluciones: Collection<int, DevolucionVenta>,
     *   total_ventas: float,
     *   iva_debito: float
     * }
     */
    private function contextoLibrosVentasElSalvador(BaseLibroIVARequest $request): array
    {
        $ventasCf = Venta::with(['cliente', 'documento', 'impuestos.impuesto'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', function ($q) {
                $q->where('nombre', 'Factura')
                    ->orWhere('nombre', 'Factura de exportación');
            })
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $ventasCf = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventasCf);

        $ventasContrib = Venta::with(['cliente', 'documento', 'impuestos.impuesto'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', fn ($q) => $q->where('nombre', 'Crédito fiscal'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $ventasContrib = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventasContrib);

        $filasContrib = $ventasContrib->map(fn (Venta $v) => [
            'total' => (float) $v->total,
            'debito_fiscal' => (float) $v->iva,
        ]);

        $devoluciones = DevolucionVenta::with(['cliente', 'venta.impuestos.impuesto'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $filasNc = $devoluciones->map(fn (DevolucionVenta $d) => [
            'total' => $d->total > 0 ? -1 * (float) $d->total : (float) $d->total,
            'debito_fiscal' => $d->iva > 0 ? -1 * (float) $d->iva : (float) $d->iva,
        ]);

        $totalesCf = $ventasCf
            ->groupBy(fn (Venta $v) => Carbon::parse($v->fecha)->format('Y-m-d'))
            ->map(function ($ventasDia) {
                $exportaciones = $ventasDia->sum(function (Venta $venta) {
                    $doc = strtolower(trim(optional($venta->documento)->nombre ?? ''));

                    return str_contains($doc, 'exportación')
                        ? $this->montoVentaPropioSinCuentaTerceros($venta)
                        : 0.0;
                });
                $totalDiario = $ventasDia->sum(fn (Venta $v) => $this->montoVentaPropioSinCuentaTerceros($v));
                $terceros = $ventasDia->sum(fn (Venta $v) => (float) ($v->cuenta_a_terceros ?? 0));

                return [
                    'total_ventas_diarias_propias' => round($totalDiario, 2),
                    'ventas_a_cuenta_de_terceros' => round($terceros, 2),
                    'exportaciones' => round($exportaciones, 2),
                ];
            });

        $totalConsumidor = round(
            (float) $totalesCf->sum('total_ventas_diarias_propias')
            + (float) $totalesCf->sum('ventas_a_cuenta_de_terceros'),
            2
        );
        $totalContrib = round((float) $filasContrib->sum('total') + (float) $filasNc->sum('total'), 2);
        $ivaDebito = round(
            (float) $filasContrib->sum('debito_fiscal')
            + (float) $filasNc->sum('debito_fiscal')
            + (float) $ventasCf->sum('iva'),
            2
        );

        return [
            'ventas' => $ventasCf->merge($ventasContrib)->values(),
            'devoluciones' => $devoluciones,
            'total_ventas' => round($totalContrib + $totalConsumidor, 2),
            'iva_debito' => $ivaDebito,
        ];
    }

    private function montoVentaPropioSinCuentaTerceros(Venta $venta): float
    {
        $total = (float) ($venta->total ?? 0);
        $ct = (float) ($venta->cuenta_a_terceros ?? 0);
        $neto = $total - $ct;

        return $neto > 0 ? $neto : 0.0;
    }

    /**
     * Desglose IVA: venta_impuestos (nombre + monto) y gravado derivado; solo ventas del libro.
     *
     * @param  Collection<int, Venta>  $ventas
     * @param  Collection<int, DevolucionVenta>  $devoluciones
     * @return array<int, array{tarifa: string, etiqueta: string, base: float, iva: float}>
     */
    private function ventasPorImpuestoDesdeVentaImpuestos(Collection $ventas, Collection $devoluciones): array
    {
        /** @var array<string, array{nombre: string, porcentaje: float, base: float, iva: float}> */
        $buckets = [];

        foreach ($ventas as $venta) {
            $this->aplicarVentaAlDesgloseImpuestos($venta, $buckets, 1.0);
        }

        foreach ($devoluciones as $devolucion) {
            $this->aplicarDevolucionAlDesgloseImpuestos($devolucion, $buckets);
        }

        $out = [];
        foreach ($buckets as $key => $b) {
            $base = round($b['base'], 2);
            $iva = round($b['iva'], 2);
            if (abs($base) < 0.00001 && abs($iva) < 0.00001) {
                continue;
            }
            $pct = (float) $b['porcentaje'];
            $tarifa = $pct > 0.0001
                ? (abs($pct - round($pct)) < 0.01 ? (string) (int) round($pct).'%' : round($pct, 2).'%')
                : strtoupper($key);
            $out[] = [
                'tarifa' => $tarifa,
                'etiqueta' => $b['nombre'],
                'base' => $base,
                'iva' => $iva,
            ];
        }

        usort($out, function (array $a, array $b) {
            $pa = (float) rtrim($a['tarifa'], '%');
            $pb = (float) rtrim($b['tarifa'], '%');
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return strcmp($a['etiqueta'], $b['etiqueta']);
        });

        return $out;
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     */
    private function aplicarVentaAlDesgloseImpuestos(Venta $venta, array &$buckets, float $signo): void
    {
        $lineas = $venta->impuestos;
        if ($lineas->isNotEmpty()) {
            foreach ($lineas as $linea) {
                $cat = $linea->impuesto;
                $nombre = trim((string) ($cat->nombre ?? 'Impuesto'));
                $pct = (float) ($cat->porcentaje ?? 0);
                $montoIva = $signo * (float) $linea->monto;
                $key = 'i'.(int) $linea->id_impuesto;
                $this->acumularIvaDesglose(
                    $buckets,
                    $key,
                    $nombre !== '' ? $nombre : 'Impuesto',
                    $pct,
                    $montoIva
                );
            }

            return;
        }

        $filaLibro = $this->filaLibroSinVentaImpuestos($venta);
        if ($filaLibro === null) {
            return;
        }
        if (abs($filaLibro['iva']) > 0.00001) {
            $this->acumularIvaDesglose(
                $buckets,
                $filaLibro['key'],
                $filaLibro['etiqueta'],
                $filaLibro['porcentaje'],
                $signo * $filaLibro['iva']
            );
        } else {
            $this->acumularBaseDesglose(
                $buckets,
                $filaLibro['key'],
                $filaLibro['etiqueta'],
                $signo * $filaLibro['base']
            );
        }
    }

    /**
     * Gravado e IVA cuando la venta está en el libro pero no tiene filas en venta_impuestos.
     *
     * @return array{key: string, etiqueta: string, porcentaje: float, base: float, iva: float}|null
     */
    private function filaLibroSinVentaImpuestos(Venta $venta): ?array
    {
        $doc = strtolower(trim(optional($venta->documento)->nombre ?? ''));
        $esCreditoFiscal = $doc === 'crédito fiscal' || $doc === 'credito fiscal';

        if (str_contains($doc, 'exportación') || str_contains($doc, 'exportacion')) {
            $base = $this->montoVentaPropioSinCuentaTerceros($venta);

            return [
                'key' => 'exp',
                'etiqueta' => 'Exportaciones (libro)',
                'porcentaje' => 0.0,
                'base' => $base,
                'iva' => 0.0,
            ];
        }

        if ((float) $venta->iva > 0.00001) {
            $base = $esCreditoFiscal
                ? (float) $venta->sub_total
                : $this->montoVentaPropioSinCuentaTerceros($venta);
            $pct = $this->porcentajeIvaEmpresa();

            return [
                'key' => 'libro_grav',
                'etiqueta' => 'Gravadas (libro)',
                'porcentaje' => $pct,
                'base' => $base,
                'iva' => (float) $venta->iva,
            ];
        }

        $base = $esCreditoFiscal
            ? (float) $venta->sub_total
            : $this->montoVentaPropioSinCuentaTerceros($venta);
        if (abs($base) < 0.00001) {
            return null;
        }

        return [
            'key' => 'libro_ex',
            'etiqueta' => 'Exentas (libro)',
            'porcentaje' => 0.0,
            'base' => $base,
            'iva' => 0.0,
        ];
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     */
    private function aplicarDevolucionAlDesgloseImpuestos(DevolucionVenta $devolucion, array &$buckets): void
    {
        $venta = $devolucion->venta;
        $lineas = $venta ? $venta->impuestos : collect();
        $ivaNc = (float) $devolucion->iva;
        $montoNc = $ivaNc > 0 ? -$ivaNc : $ivaNc;

        if ($lineas->isNotEmpty() && abs($ivaNc) > 0.00001) {
            $totalPadre = (float) $lineas->sum('monto');
            if (abs($totalPadre) > 0.00001) {
                foreach ($lineas as $linea) {
                    $cat = $linea->impuesto;
                    $nombre = trim((string) ($cat->nombre ?? 'Impuesto'));
                    $pct = (float) ($cat->porcentaje ?? 0);
                    $proporcion = (float) $linea->monto / $totalPadre;
                    $key = 'i'.(int) $linea->id_impuesto;
                    $this->acumularIvaDesglose(
                        $buckets,
                        $key,
                        $nombre !== '' ? $nombre.' (NC)' : 'Impuesto (NC)',
                        $pct,
                        round($montoNc * $proporcion, 5)
                    );
                }
            }

            return;
        }

        $subTotal = ($devolucion->sub_total ?? 0) > 0 ? -1 * (float) $devolucion->sub_total : (float) ($devolucion->sub_total ?? 0);
        $exenta = ($devolucion->exenta ?? 0) > 0 ? -1 * (float) $devolucion->exenta : (float) ($devolucion->exenta ?? 0);

        if (abs($montoNc) > 0.00001) {
            $pct = $this->porcentajeIvaEmpresa();
            $this->acumularIvaDesglose($buckets, 'libro_grav_nc', 'Gravadas (libro NC)', $pct, $montoNc);
        } elseif (abs($subTotal) >= 0.01) {
            $this->acumularBaseDesglose($buckets, 'libro_grav_nc', 'Gravadas (libro NC)', $subTotal);
        } elseif (abs($exenta) >= 0.01) {
            $this->acumularBaseDesglose($buckets, 'libro_ex_nc', 'Exentas (libro NC)', $exenta);
        }
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     */
    private function acumularIvaDesglose(array &$buckets, string $key, string $nombre, float $porcentaje, float $montoIva): void
    {
        if (abs($montoIva) < 0.00001) {
            return;
        }
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'nombre' => $nombre,
                'porcentaje' => $porcentaje,
                'base' => 0.0,
                'iva' => 0.0,
            ];
        }
        $buckets[$key]['iva'] += $montoIva;
        $buckets[$key]['base'] += $porcentaje > 0.0001
            ? $montoIva / ($porcentaje / 100.0)
            : 0.0;
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     */
    private function acumularBaseDesglose(array &$buckets, string $key, string $nombre, float $base): void
    {
        if (abs($base) < 0.00001) {
            return;
        }
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'nombre' => $nombre,
                'porcentaje' => 0.0,
                'base' => 0.0,
                'iva' => 0.0,
            ];
        }
        $buckets[$key]['base'] += $base;
    }

    private function porcentajeIvaEmpresa(): float
    {
        $empresa = $this->feHelper->obtenerEmpresa();
        $iva = (float) (optional($empresa)->iva ?? 0);

        return $iva > 0.0001 ? $iva : 13.0;
    }

    /**
     * Compras del periodo (tabla compras + compra_impuestos; sin devoluciones de compra).
     *
     * @return Collection<int, Compra>
     */
    private function comprasLibroFiscal(BaseLibroIVARequest $request): Collection
    {
        return Compra::query()
            ->with(['impuestos.impuesto'])
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * Desglose IVA desde compra_impuestos; solo compras registradas (sin devoluciones).
     *
     * @param  Collection<int, Compra>  $compras
     * @return array<int, array{tarifa: string, etiqueta: string, base: float, iva: float}>
     */
    private function comprasPorImpuestoDesdeCompraImpuestos(Collection $compras): array
    {
        /** @var array<string, array{nombre: string, porcentaje: float, base: float, iva: float}> */
        $buckets = [];

        foreach ($compras as $compra) {
            $this->aplicarCompraAlDesgloseImpuestos($compra, $buckets, 1.0);
        }

        return $this->formatearBucketsDesgloseImpuestos($buckets);
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     */
    private function aplicarCompraAlDesgloseImpuestos(Compra $compra, array &$buckets, float $signo): void
    {
        $lineas = $compra->impuestos;
        if ($lineas->isNotEmpty()) {
            foreach ($lineas as $linea) {
                $cat = $linea->impuesto;
                $nombre = trim((string) ($cat->nombre ?? 'Impuesto'));
                $pct = (float) ($cat->porcentaje ?? 0);
                $montoIva = $signo * (float) $linea->monto;
                $key = 'i'.(int) $linea->id_impuesto;
                $this->acumularIvaDesglose(
                    $buckets,
                    $key,
                    $nombre !== '' ? $nombre : 'Impuesto',
                    $pct,
                    $montoIva
                );
            }

            return;
        }

        $filaLibro = $this->filaLibroSinCompraImpuestos($compra);
        if ($filaLibro === null) {
            return;
        }
        if (abs($filaLibro['iva']) > 0.00001) {
            $this->acumularIvaDesglose(
                $buckets,
                $filaLibro['key'],
                $filaLibro['etiqueta'],
                $filaLibro['porcentaje'],
                $signo * $filaLibro['iva']
            );
        } else {
            $this->acumularBaseDesglose(
                $buckets,
                $filaLibro['key'],
                $filaLibro['etiqueta'],
                $signo * $filaLibro['base']
            );
        }
    }

    /**
     * Gravado e IVA cuando la compra no tiene filas en compra_impuestos.
     *
     * @return array{key: string, etiqueta: string, porcentaje: float, base: float, iva: float}|null
     */
    private function filaLibroSinCompraImpuestos(Compra $compra): ?array
    {
        if ((float) $compra->iva > 0.00001) {
            $base = (float) $compra->sub_total;
            $pct = $this->porcentajeIvaEmpresa();

            return [
                'key' => 'libro_grav_compra',
                'etiqueta' => 'Gravadas (libro compras)',
                'porcentaje' => $pct,
                'base' => $base,
                'iva' => (float) $compra->iva,
            ];
        }

        $base = (float) $compra->sub_total;
        if (abs($base) < 0.00001) {
            return null;
        }

        return [
            'key' => 'libro_ex_compra',
            'etiqueta' => 'Exentas (libro compras)',
            'porcentaje' => 0.0,
            'base' => $base,
            'iva' => 0.0,
        ];
    }

    /**
     * @param  array<string, array{nombre: string, porcentaje: float, base: float, iva: float}>  $buckets
     * @return array<int, array{tarifa: string, etiqueta: string, base: float, iva: float}>
     */
    private function formatearBucketsDesgloseImpuestos(array $buckets): array
    {
        $out = [];
        foreach ($buckets as $key => $b) {
            $base = round($b['base'], 2);
            $iva = round($b['iva'], 2);
            if (abs($base) < 0.00001 && abs($iva) < 0.00001) {
                continue;
            }
            $pct = (float) $b['porcentaje'];
            $tarifa = $pct > 0.0001
                ? (abs($pct - round($pct)) < 0.01 ? (string) (int) round($pct).'%' : round($pct, 2).'%')
                : strtoupper($key);
            $out[] = [
                'tarifa' => $tarifa,
                'etiqueta' => $b['nombre'],
                'base' => $base,
                'iva' => $iva,
            ];
        }

        usort($out, function (array $a, array $b) {
            $pa = (float) rtrim($a['tarifa'], '%');
            $pb = (float) rtrim($b['tarifa'], '%');
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return strcmp($a['etiqueta'], $b['etiqueta']);
        });

        return $out;
    }

    private function buildHondurasYOtros(BaseLibroIVARequest $request, string $paisNombre): array
    {
        $expV = new LibroVentasHondurasExport();
        $expV->filter($request);
        $filasV = collect($expV->rowsForApi());

        $totalVentas = round((float) $filasV->sum(function ($r) {
            return (float) ($r['importe_exenta'] ?? 0) + (float) ($r['importe_gravada'] ?? 0)
                + (float) ($r['importe_exonerada'] ?? 0) + (float) ($r['impuesto_ventas'] ?? 0)
                + (float) ($r['importe_exportacion'] ?? 0);
        }), 2);

        $ivaDebito = round((float) $filasV->sum(fn ($r) => (float) ($r['impuesto_ventas'] ?? 0)), 2);

        $expC = new LibroComprasHondurasExport();
        $expC->filter($request);

        $totalCompras = 0.0;
        $totalGastos = 0.0;
        $ivaCreditoCompras = 0.0;
        $ivaCreditoGastos = 0.0;
        $ivaCreditoDevoluciones = 0.0;

        foreach ($expC->collection() as $item) {
            $r = $item->registro;
            $m = (int) $item->mult;
            $imp = (float) ($r->iva ?? 0) * $m;
            $tot = (float) ($r->total ?? 0) * $m;
            if ($r instanceof Gasto) {
                $ivaCreditoGastos += $imp;
                $totalGastos += $tot;
            } elseif ($r instanceof DevolucionCompra) {
                $ivaCreditoDevoluciones += $imp;
                $totalCompras += $tot;
            } else {
                $ivaCreditoCompras += $imp;
                $totalCompras += $tot;
            }
        }

        $ivaCreditoCompras = round($ivaCreditoCompras, 2);
        $ivaCreditoGastos = round($ivaCreditoGastos, 2);
        $ivaCreditoDevoluciones = round($ivaCreditoDevoluciones, 2);
        $ivaCredito = round($ivaCreditoCompras + $ivaCreditoGastos + $ivaCreditoDevoluciones, 2);
        $totalCompras = round($totalCompras, 2);
        $totalGastos = round($totalGastos, 2);

        $comprasLibro = $this->comprasLibroFiscal($request);

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $totalVentas,
                'compras' => $totalCompras,
                'compras_sin_devoluciones' => round((float) $comprasLibro->sum(fn (Compra $c) => (float) $c->total), 2),
                'gastos' => $totalGastos,
            ],
            'ventas_por_impuesto' => $this->ventasPorImpuestoDesdeVentaImpuestos(
                $this->ventasLibroHonduras($request),
                $this->devolucionesLibroHonduras($request)
            ),
            'compras_por_impuesto' => $this->comprasPorImpuestoDesdeCompraImpuestos($comprasLibro),
            'iva' => [
                'iva_a_favor' => $ivaCredito,
                'credito_fiscal_compras' => $ivaCreditoCompras,
                'credito_fiscal_gastos' => $ivaCreditoGastos,
                'credito_fiscal_devoluciones_compras' => $ivaCreditoDevoluciones,
                'iva_en_contra' => $ivaDebito,
                'diferencia_estimada_pago_iva' => round($ivaDebito - $ivaCredito, 2),
            ],
            'pago_a_cuenta_iva' => [
                'aplica' => false,
                'monto' => null,
                'descripcion' => null,
            ],
        ];
    }

    private function buildElSalvador(BaseLibroIVARequest $request, string $paisNombre): array
    {
        $ctxVentas = $this->contextoLibrosVentasElSalvador($request);

        $libroCompras = $this->filasLibroComprasElSalvador($request);
        $creditoCompras = round((float) $libroCompras->where('origen', 'compra')->sum('credito_fiscal'), 2);
        $creditoGastos = round((float) $libroCompras->where('origen', 'gasto')->sum('credito_fiscal'), 2);
        $creditoDevoluciones = round((float) $libroCompras->where('origen', 'devolucion')->sum('credito_fiscal'), 2);
        $ivaCredito = round($creditoCompras + $creditoGastos + $creditoDevoluciones, 2);
        $anticipoIva = round((float) $libroCompras->sum('anticipo_iva_percibido'), 2);

        $totalCompras = round(
            (float) $libroCompras->whereIn('origen', ['compra', 'devolucion'])->sum('total')
            + (float) $libroCompras->whereIn('origen', ['compra', 'devolucion'])->sum('sujeto_excluido'),
            2
        );

        $totalGastos = round(
            (float) $libroCompras->where('origen', 'gasto')->sum('total')
            + (float) $libroCompras->where('origen', 'gasto')->sum('sujeto_excluido'),
            2
        );

        $comprasLibro = $this->comprasLibroFiscal($request);

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $ctxVentas['total_ventas'],
                'compras' => $totalCompras,
                'compras_sin_devoluciones' => round((float) $comprasLibro->sum(fn (Compra $c) => (float) $c->total), 2),
                'gastos' => $totalGastos,
            ],
            'ventas_por_impuesto' => $this->ventasPorImpuestoDesdeVentaImpuestos(
                $ctxVentas['ventas'],
                $ctxVentas['devoluciones']
            ),
            'compras_por_impuesto' => $this->comprasPorImpuestoDesdeCompraImpuestos($comprasLibro),
            'iva' => [
                'iva_a_favor' => $ivaCredito,
                'credito_fiscal_compras' => $creditoCompras,
                'credito_fiscal_gastos' => $creditoGastos,
                'credito_fiscal_devoluciones_compras' => $creditoDevoluciones,
                'iva_en_contra' => $ctxVentas['iva_debito'],
                'diferencia_estimada_pago_iva' => round($ctxVentas['iva_debito'] - $ivaCredito, 2),
            ],
            'pago_a_cuenta_iva' => [
                'aplica' => true,
                'monto' => $anticipoIva,
                'descripcion' => 'Anticipo / percepción a cuenta de impuesto (libro de compras y gastos)',
            ],
        ];
    }

    /**
     * Replica el armado de filas de {@see LibrosIVAController::compras} (El Salvador) para totales.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function filasLibroComprasElSalvador(BaseLibroIVARequest $request): Collection
    {
        $compras = Compra::with(['proveedor'])
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $comprasData = $compras->map(function ($compra) {
            $data = [
                'fecha' => $compra->fecha,
                'tipo_documento' => $compra->tipo_documento,
                'compras_exentas' => 0,
                'importaciones_exentas' => 0,
                'compras_gravadas' => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal' => 0,
                'anticipo_iva_percibido' => 0,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total' => 0,
                'sujeto_excluido' => 0,
                'origen' => 'compra',
            ];

            if ($compra->tipo_documento === 'Sujeto excluido') {
                $data['sujeto_excluido'] = $compra->total;
            } else {
                $data['compras_gravadas'] = $compra->sub_total;
                $data['credito_fiscal'] = $compra->iva;
                $data['total'] = $compra->total;
            }

            return $data;
        });

        $gastos = Gasto::with(['proveedor'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $gastosData = $gastos->map(function ($gasto) {
            $data = [
                'fecha' => $gasto->fecha,
                'tipo_documento' => $gasto->tipo_documento,
                'compras_exentas' => $gasto->total_otros_impuestos,
                'importaciones_exentas' => 0,
                'compras_gravadas' => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal' => 0,
                'anticipo_iva_percibido' => $gasto->percepcion,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total' => 0,
                'sujeto_excluido' => 0,
                'origen' => 'gasto',
            ];

            if ($gasto->tipo_documento === 'Sujeto excluido') {
                $data['sujeto_excluido'] = $gasto->total;
            } else {
                $data['compras_gravadas'] = $gasto->sub_total;
                $data['credito_fiscal'] = $gasto->iva;
                $data['total'] = $gasto->total;
            }

            return $data;
        });

        $devoluciones = DevolucionCompra::with(['proveedor'])
            ->where('enable', true)
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereIn('tipo_documento', ['Crédito fiscal', 'Factura', 'Factura de exportación', 'Importación', 'Nota de crédito', 'Nota de débito'])
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $devolucionesData = $devoluciones->map(function ($devolucion) {
            $data = [
                'fecha' => $devolucion->fecha,
                'tipo_documento' => $devolucion->tipo_documento,
                'compras_exentas' => 0,
                'importaciones_exentas' => 0,
                'compras_gravadas' => 0,
                'importaciones_gravadas' => 0,
                'credito_fiscal' => 0,
                'anticipo_iva_percibido' => $devolucion->percepcion * -1,
                'compras_cuenta_terceros' => 0,
                'credito_cuenta_terceros' => 0,
                'total' => 0,
                'sujeto_excluido' => 0,
                'origen' => 'devolucion',
            ];

            if ($devolucion->tipo_documento === 'Sujeto excluido') {
                $data['sujeto_excluido'] = $devolucion->total * -1;
            } else {
                $data['compras_gravadas'] = $devolucion->sub_total * -1;
                $data['credito_fiscal'] = $devolucion->iva * -1;
                $data['total'] = $devolucion->total * -1;
            }

            return $data;
        });

        return $comprasData->merge($gastosData)->merge($devolucionesData);
    }
}
