<?php

namespace App\Services\Contabilidad;

use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * Libro de consumidor final (SV) agrupado por día.
 * ponytail: el correlativo sale de ventas.codigo_generacion; no leer dte/S3.
 */
class LibroConsumidoresFilas
{
    public static function consulta(object $request, bool $facturacionElectronica): Builder
    {
        return self::queryConsumidor($request, $facturacionElectronica)
            ->select([
                'ventas.fecha',
                'ventas.correlativo',
                'ventas.codigo_generacion',
                'ventas.total',
                'ventas.cuenta_a_terceros',
                'ventas.exenta',
                'ventas.gravada',
                'ventas.sub_total',
                'ventas.no_sujeta',
                'ventas.iva',
                'documentos.nombre as documento_nombre',
            ])
            ->toBase();
    }

    /**
     * Eventos de retorno (tipo 18) sellados sobre Factura / Factura de exportación: restan de las ventas del día del retorno.
     */
    public static function consultaRetornos(object $request): Builder
    {
        return DevolucionVenta::query()
            ->join('ventas', 'ventas.id', '=', 'devoluciones_venta.id_venta')
            ->join('documentos', 'documentos.id', '=', 'ventas.id_documento')
            ->where('devoluciones_venta.enable', true)
            ->where('devoluciones_venta.tipo_dte', '18')
            ->whereNotNull('devoluciones_venta.sello_mh')
            ->where('ventas.estado', '!=', 'Anulada')
            ->whereIn('documentos.nombre', ['Factura', 'Factura de exportación'])
            ->when($request->id_sucursal, fn ($q) => $q->where('devoluciones_venta.id_sucursal', $request->id_sucursal))
            ->whereBetween('devoluciones_venta.fecha', [$request->inicio, $request->fin])
            ->select([
                'devoluciones_venta.fecha',
                'devoluciones_venta.total',
                'devoluciones_venta.cuenta_a_terceros',
                'devoluciones_venta.exenta',
                'devoluciones_venta.sub_total',
                'devoluciones_venta.no_sujeta',
                'devoluciones_venta.iva',
                'documentos.nombre as documento_nombre',
            ])
            ->toBase();
    }

    /**
     * Eventos de retorno para el anexo CSV: una fila tipo 18 con montos positivos (igual que NC 05 en contribuyentes).
     */
    public static function retornosAnexo(object $request): \Illuminate\Support\Collection
    {
        return DevolucionVenta::with(['venta.documento', 'detalles.producto'])
            ->where('enable', true)
            ->where('tipo_dte', '18')
            ->whereNotNull('sello_mh')
            ->whereHas('venta', fn ($q) => $q->where('estado', '!=', 'Anulada')
                ->whereHas('documento', fn ($d) => $d->whereIn('nombre', ['Factura', 'Factura de exportación'])))
            ->when($request->id_sucursal, fn ($q) => $q->where('id_sucursal', $request->id_sucursal))
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get()
            ->map(function ($retorno) {
                $retorno->tipo_anexo = '18';
                $retorno->documento_nombre = optional(optional($retorno->venta)->documento)->nombre;
                $retorno->codigo_generacion = $retorno->dte['identificacion']['codigoGeneracion'] ?? $retorno->codigo_generacion;
                $retorno->numero_control = null;
                $retorno->correlativo = '';
                $retorno->tipo_operacion = optional($retorno->venta)->tipo_operacion;
                $retorno->tipo_renta = optional($retorno->venta)->tipo_renta;

                return $retorno;
            });
    }

    /** Ventas del anexo CSV (sin columna dte / S3). */
    public static function consultaAnexo(object $request, bool $facturacionElectronica): EloquentBuilder
    {
        return self::queryConsumidor($request, $facturacionElectronica)
            ->select([
                'ventas.id',
                'ventas.fecha',
                'ventas.correlativo',
                'ventas.codigo_generacion',
                'ventas.numero_control',
                'ventas.sello_mh',
                'ventas.total',
                'ventas.cuenta_a_terceros',
                'ventas.exenta',
                'ventas.gravada',
                'ventas.sub_total',
                'ventas.no_sujeta',
                'ventas.iva',
                'ventas.tipo_operacion',
                'ventas.tipo_renta',
                'documentos.nombre as documento_nombre',
            ])
            ->orderByDesc('ventas.fecha');
    }

    public static function codigoAnexoSinGuiones(?string $codigo): string
    {
        return str_replace('-', '', trim((string) $codigo));
    }

    /**
     * @return array{exenta: float, gravada: float, no_sujeta: float, total_propio: float, exportacion: float}
     */
    public static function montosAnexoConsumidor(object $venta, bool $esFacturaExportacion): array
    {
        $totalPropio = LibroIvaMontosHelper::montoVentaPropioSinCuentaTerceros($venta);

        if ($esFacturaExportacion) {
            return [
                'exenta' => 0.0,
                'gravada' => 0.0,
                'no_sujeta' => 0.0,
                'total_propio' => $totalPropio,
                'exportacion' => $totalPropio,
            ];
        }

        return [
            'exenta' => LibroIvaMontosHelper::ventasExentas($venta),
            'gravada' => LibroIvaMontosHelper::ventasGravadas($venta),
            'no_sujeta' => LibroIvaMontosHelper::ventasNoSujetas($venta),
            'total_propio' => $totalPropio,
            'exportacion' => 0.0,
        ];
    }

    private static function queryConsumidor(object $request, bool $facturacionElectronica): EloquentBuilder
    {
        return Venta::query()
            ->join('documentos', 'documentos.id', '=', 'ventas.id_documento')
            ->where('ventas.estado', '!=', 'Anulada')
            ->whereIn('documentos.nombre', ['Factura', 'Factura de exportación'])
            ->where('ventas.cotizacion', 0)
            ->when($request->id_sucursal, function ($query) use ($request) {
                $query->where('ventas.id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('ventas.fecha', [$request->inicio, $request->fin])
            ->when($facturacionElectronica, function ($query) {
                $query->whereNotNull('ventas.sello_mh')
                    ->where('ventas.sello_mh', '!=', '');
            });
    }

    public static function codigo(object $venta, bool $facturacionElectronica): string
    {
        if ($facturacionElectronica) {
            $codigo = trim((string) ($venta->codigo_generacion ?? ''));
            if ($codigo !== '') {
                return $codigo;
            }
        }

        return trim((string) ($venta->correlativo ?? ''));
    }

    /**
     * @param  iterable  $retornos  Eventos de retorno del período (ver consultaRetornos): se restan del día en que se emitieron.
     */
    public static function filas(iterable $ventas, bool $facturacionElectronica, iterable $retornos = []): array
    {
        $retornosPorDia = collect($retornos)->groupBy(fn ($r) => Carbon::parse($r->fecha)->format('Y-m-d'));
        $ventasPorDia = collect($ventas)->groupBy(fn ($venta) => Carbon::parse($venta->fecha)->format('Y-m-d'));

        return $ventasPorDia->keys()->merge($retornosPorDia->keys())->unique()
            ->mapWithKeys(fn ($fecha) => [$fecha => $ventasPorDia->get($fecha, collect())])
            ->map(function ($ventasDia, $fecha) use ($facturacionElectronica, $retornosPorDia) {
                $retornosDia = $retornosPorDia->get($fecha, collect());
                // Ventas del día menos eventos de retorno del día.
                $neto = fn (callable $monto) => $ventasDia->sum($monto) - $retornosDia->sum($monto);

                $ventasOrdenadasPorCorrelativo = $ventasDia->sortBy(function ($venta) {
                    return trim((string) $venta->correlativo);
                });

                $ventasOrdenadasPorCodigo = $ventasDia->sortBy(function ($venta) use ($facturacionElectronica) {
                    return self::codigo($venta, $facturacionElectronica);
                });

                $exportaciones = $neto(function ($venta) {
                    return self::esExportacion($venta)
                        ? LibroIvaMontosHelper::montoVentaPropioSinCuentaTerceros($venta)
                        : 0;
                });

                $ventasExentas = $neto(function ($venta) {
                    if (self::esExportacion($venta)) {
                        return 0;
                    }

                    return LibroIvaMontosHelper::ventasExentas($venta);
                });

                $ventasGravadas = $neto(function ($venta) {
                    if (self::esExportacion($venta)) {
                        return 0;
                    }

                    return LibroIvaMontosHelper::ventasGravadas($venta);
                });

                $ventasTerceros = $neto(function ($venta) {
                    return (float) $venta->cuenta_a_terceros;
                });

                $totalDiario = $neto(function ($venta) {
                    return LibroIvaMontosHelper::montoVentaPropioSinCuentaTerceros($venta);
                });

                $primeraVenta = $ventasOrdenadasPorCodigo->first();
                $ultimaVenta = $ventasOrdenadasPorCodigo->last();
                $primeraPorCorrelativo = $ventasOrdenadasPorCorrelativo->first();
                $correlativoInicial = $primeraPorCorrelativo ? $primeraPorCorrelativo->correlativo : '';

                return [
                    'fecha' => $fecha,
                    'correlativo_inicial' => $primeraVenta ? self::codigo($primeraVenta, $facturacionElectronica) : null,
                    'correlativo_final' => $ultimaVenta ? self::codigo($ultimaVenta, $facturacionElectronica) : null,
                    'ventas_exentas' => round($ventasExentas, 2),
                    'ventas_internas_gravadas' => round($ventasGravadas, 2),
                    'exportaciones' => round($exportaciones, 2),
                    'total_ventas_diarias_propias' => round($totalDiario, 2),
                    'ventas_a_cuenta_de_terceros' => round($ventasTerceros, 2),
                    'correlativo_orden' => trim((string) $correlativoInicial),
                ];
            })
            ->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo_orden'] ?? ''];
            })
            ->values()
            ->all();
    }

    private static function esExportacion(object $venta): bool
    {
        $documentoNombre = trim((string) ($venta->documento_nombre ?? ''));

        return strtolower($documentoNombre) === 'factura de exportación';
    }
}
