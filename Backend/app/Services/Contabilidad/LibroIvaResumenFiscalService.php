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
use App\Services\Contabilidad\CostaRica\ReporteDetalleIvaCrService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Illuminate\Support\Collection;

/**
 * Resumen fiscal unificado (ventas, compras, gastos, IVA, desglose por tarifa cuando aplica).
 */
final class LibroIvaResumenFiscalService
{
    private FacturacionElectronicaHelperService $feHelper;

    private ReporteDetalleIvaCrService $reporteDetalleIvaCrService;

    public function __construct(
        FacturacionElectronicaHelperService $feHelper,
        ReporteDetalleIvaCrService $reporteDetalleIvaCrService
    ) {
        $this->feHelper = $feHelper;
        $this->reporteDetalleIvaCrService = $reporteDetalleIvaCrService;
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

    private function idSucursalInt(?string $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

    private function buildCostaRica(BaseLibroIVARequest $request, string $paisNombre): array
    {
        $idSuc = $this->idSucursalInt($request->id_sucursal ? (string) $request->id_sucursal : null);

        $filasV = $this->reporteDetalleIvaCrService->filasVentas($request->inicio, $request->fin, $idSuc);
        $totV = $this->reporteDetalleIvaCrService->totales($filasV);

        $filasC = $this->reporteDetalleIvaCrService->filasCompras($request->inicio, $request->fin, $idSuc);
        $totC = $this->reporteDetalleIvaCrService->totales($filasC);

        $basesV = $this->sumaBasesCr($totV);
        $ivaV = $this->sumaIvaCr($totV);
        $ivaDevV = (float) ($totV['iva_devuelto'] ?? 0);

        $basesC = $this->sumaBasesCr($totC);
        $ivaC = $this->sumaIvaCr($totC);
        $ivaDevC = (float) ($totC['iva_devuelto'] ?? 0);

        $totalVentasDoc = round($basesV + $ivaV - $ivaDevV, 2);
        $totalComprasModelo = round((float) $this->sumTotalComprasCr($request), 2);
        $totalGastosModelo = round((float) $this->sumTotalGastosCr($request), 2);

        $ivaDebito = round($ivaV - $ivaDevV, 2);
        $ivaCredito = round($ivaC - $ivaDevC, 2);

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $totalVentasDoc,
                'compras' => $totalComprasModelo,
                'gastos' => $totalGastosModelo,
            ],
            'ventas_por_impuesto' => $this->ventasPorImpuestoDesdeTotalesCr($totV),
            'iva' => [
                'iva_a_favor' => $ivaCredito,
                'credito_fiscal_compras' => null,
                'credito_fiscal_gastos' => null,
                'credito_fiscal_devoluciones_compras' => null,
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

    /** @param  array<string, float>  $t */
    private function sumaBasesCr(array $t): float
    {
        $s = 0.0;
        $porTarifa = 0.0;
        foreach (['13', '8', '4', '2', '1'] as $r) {
            $v = (float) ($t['subtotal_'.$r] ?? 0);
            $s += $v;
            $porTarifa += $v;
        }
        $s += (float) ($t['subtotal_exonerado'] ?? 0);
        $s += (float) ($t['subtotal_exento'] ?? 0);
        // subtotal_gravado (total_taxed del DTE) ya suele estar en las columnas por tarifa; solo sumar el faltante.
        $gravado = (float) ($t['subtotal_gravado'] ?? 0);
        $sinTarifa = round($gravado - $porTarifa, 5);
        if ($sinTarifa > 0.00001) {
            $s += $sinTarifa;
        } elseif (abs($porTarifa) < 0.00001 && abs($gravado) > 0.00001) {
            $s += $gravado;
        }

        return $s;
    }

    /** @param  array<string, float>  $t */
    private function sumaIvaCr(array $t): float
    {
        $s = 0.0;
        foreach (['13', '8', '4', '2', '1'] as $r) {
            $s += (float) ($t['iva_'.$r] ?? 0);
        }

        return $s;
    }

    /** @param  array<string, float>  $tot */
    private function ventasPorImpuestoDesdeTotalesCr(array $tot): array
    {
        $out = [];

        $exento = (float) ($tot['subtotal_exento'] ?? 0);
        if (abs($exento) >= 0.00001) {
            $out[] = [
                'tarifa' => 'EX',
                'etiqueta' => 'Exento',
                'base' => round($exento, 2),
                'iva' => 0.0,
            ];
        }

        $exonerado = (float) ($tot['subtotal_exonerado'] ?? 0);
        if (abs($exonerado) >= 0.00001) {
            $out[] = [
                'tarifa' => 'EXO',
                'etiqueta' => 'Exonerado',
                'base' => round($exonerado, 2),
                'iva' => 0.0,
            ];
        }

        $porTarifa = 0.0;
        foreach (['13', '8', '4', '2', '1'] as $r) {
            $base = (float) ($tot['subtotal_'.$r] ?? 0);
            $iva = (float) ($tot['iva_'.$r] ?? 0);
            $porTarifa += $base;
            if (abs($base) < 0.00001 && abs($iva) < 0.00001) {
                continue;
            }
            $out[] = [
                'tarifa' => $r.'%',
                'etiqueta' => 'Tarifa '.$r.'%',
                'base' => round($base, 2),
                'iva' => round($iva, 2),
            ];
        }

        $gravado = (float) ($tot['subtotal_gravado'] ?? 0);
        $sinTarifa = round($gravado - $porTarifa, 5);
        if ($sinTarifa > 0.00001) {
            $out[] = [
                'tarifa' => 'GR',
                'etiqueta' => 'Gravado (sin tarifa en detalle)',
                'base' => round($sinTarifa, 2),
                'iva' => 0.0,
            ];
        } elseif (abs($porTarifa) < 0.00001 && abs($gravado) >= 0.00001) {
            $out[] = [
                'tarifa' => 'GR',
                'etiqueta' => 'Gravado',
                'base' => round($gravado, 2),
                'iva' => 0.0,
            ];
        }

        return $out;
    }

    private function sumTotalComprasCr(BaseLibroIVARequest $request): float
    {
        $q = Compra::query()
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$request->inicio, $request->fin]);
        if ($request->id_sucursal) {
            $q->where('id_sucursal', $request->id_sucursal);
        }

        return (float) $q->sum('total');
    }

    private function sumTotalGastosCr(BaseLibroIVARequest $request): float
    {
        $q = Gasto::query()
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$request->inicio, $request->fin]);
        if ($request->id_sucursal) {
            $q->where('id_sucursal', $request->id_sucursal);
        }

        return (float) $q->sum('total');
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
        $baseGravada = round((float) $filasV->sum(fn ($r) => (float) ($r['importe_gravada'] ?? 0)), 2);

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

        $ventasPorImp = [];
        if (abs($ivaDebito) > 0.00001 || abs($baseGravada) > 0.00001) {
            $ventasPorImp[] = [
                'tarifa' => 'ISV',
                'etiqueta' => 'Impuesto sobre ventas (libro fiscal)',
                'base' => $baseGravada,
                'iva' => $ivaDebito,
            ];
        }

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $totalVentas,
                'compras' => $totalCompras,
                'gastos' => $totalGastos,
            ],
            'ventas_por_impuesto' => $ventasPorImp,
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
        $ventasCf = Venta::with(['cliente', 'documento'])
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

        $ventasContrib = Venta::with(['cliente', 'documento'])
            ->where('estado', '!=', 'Anulada')
            ->whereHas('documento', fn ($q) => $q->where('nombre', 'Crédito fiscal'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->where('cotizacion', 0)
            ->get();

        $ventasContrib = $this->feHelper->filtrarVentasPorFacturacionElectronica($ventasContrib);

        $totalVentas = round((float) $ventasCf->sum('total') + (float) $ventasContrib->sum('total'), 2);

        $ivaDebitoVentas = (float) $ventasCf->sum('iva') + (float) $ventasContrib->sum('iva');

        $devoluciones = DevolucionVenta::with(['cliente', 'venta'])
            ->where('enable', true)
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada'))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $ivaDebitoDevoluciones = (float) $devoluciones->sum(function ($d) {
            return $d->iva > 0 ? -1 * (float) $d->iva : (float) $d->iva;
        });

        $ivaDebito = round($ivaDebitoVentas + $ivaDebitoDevoluciones, 2);

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

        $baseGravadaSv = round((float) $ventasCf->sum(fn ($v) => $v->iva > 0 ? (float) $v->sub_total : 0)
            + (float) $ventasContrib->sum(fn ($v) => $v->iva > 0 ? (float) $v->sub_total : 0)
            + (float) $devoluciones->sum(fn ($d) => $d->sub_total > 0 ? -1 * (float) $d->sub_total : (float) $d->sub_total), 2);

        $ventasPorImp = [];
        if (abs($ivaDebito) > 0.00001 || abs($baseGravadaSv) > 0.00001) {
            $ventasPorImp[] = [
                'tarifa' => '13%',
                'etiqueta' => 'Débito fiscal IVA (ventas gravadas y NC)',
                'base' => $baseGravadaSv,
                'iva' => $ivaDebito,
            ];
        }

        return [
            'pais' => $paisNombre,
            'periodo' => ['inicio' => $request->inicio, 'fin' => $request->fin],
            'totales' => [
                'ventas' => $totalVentas,
                'compras' => $totalCompras,
                'gastos' => $totalGastos,
            ],
            'ventas_por_impuesto' => $ventasPorImp,
            'iva' => [
                'iva_a_favor' => $ivaCredito,
                'credito_fiscal_compras' => $creditoCompras,
                'credito_fiscal_gastos' => $creditoGastos,
                'credito_fiscal_devoluciones_compras' => $creditoDevoluciones,
                'iva_en_contra' => $ivaDebito,
                'diferencia_estimada_pago_iva' => round($ivaDebito - $ivaCredito, 2),
            ],
            'pago_a_cuenta_iva' => [
                'aplica' => true,
                'monto' => $anticipoIva,
                'descripcion' => 'Anticipo / percepción a cuenta de IVA (libro de compras y gastos)',
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
