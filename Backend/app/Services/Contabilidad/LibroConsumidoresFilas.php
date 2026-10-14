<?php

namespace App\Services\Contabilidad;

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

    public static function filas(iterable $ventas, bool $facturacionElectronica): array
    {
        return collect($ventas)
            ->groupBy(function ($venta) {
                return Carbon::parse($venta->fecha)->format('Y-m-d');
            })
            ->map(function ($ventasDia, $fecha) use ($facturacionElectronica) {
                $ventasOrdenadasPorCorrelativo = $ventasDia->sortBy(function ($venta) {
                    return trim((string) $venta->correlativo);
                });

                $ventasOrdenadasPorCodigo = $ventasDia->sortBy(function ($venta) use ($facturacionElectronica) {
                    return self::codigo($venta, $facturacionElectronica);
                });

                $exportaciones = $ventasDia->sum(function ($venta) {
                    return self::esExportacion($venta)
                        ? LibroIvaMontosHelper::montoVentaPropioSinCuentaTerceros($venta)
                        : 0;
                });

                $ventasExentas = $ventasDia->sum(function ($venta) {
                    if (self::esExportacion($venta)) {
                        return 0;
                    }

                    return LibroIvaMontosHelper::ventasExentas($venta);
                });

                $ventasGravadas = $ventasDia->sum(function ($venta) {
                    if (self::esExportacion($venta)) {
                        return 0;
                    }

                    return LibroIvaMontosHelper::ventasGravadas($venta);
                });

                $ventasTerceros = $ventasDia->sum(function ($venta) {
                    return (float) $venta->cuenta_a_terceros;
                });

                $totalDiario = $ventasDia->sum(function ($venta) {
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
