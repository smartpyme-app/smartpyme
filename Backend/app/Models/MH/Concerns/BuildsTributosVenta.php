<?php

namespace App\Models\MH\Concerns;

use Illuminate\Support\Collection;

trait BuildsTributosVenta
{
    protected function documentoFiscal()
    {
        if (!empty($this->devolucion)) {
            return $this->devolucion;
        }

        return $this->venta;
    }

    /**
     * Filas con monto e impuesto del catálogo (venta_impuestos o prorrateo desde venta original).
     */
    protected function filasImpuestosDocumento(): Collection
    {
        $doc = $this->documentoFiscal();

        if (method_exists($doc, 'impuestos')) {
            if (!$doc->relationLoaded('impuestos')) {
                $doc->load('impuestos.impuesto');
            }

            $filas = $doc->impuestos->filter(fn ($vi) => (float) $vi->monto > 0);
            if ($filas->isNotEmpty()) {
                return $filas;
            }
        }

        if (!empty($this->devolucion)) {
            return $this->filasImpuestosDesdeVentaOriginal($this->devolucion);
        }

        return collect();
    }

    protected function filasImpuestosDesdeVentaOriginal($devolucion): Collection
    {
        if (!$devolucion->relationLoaded('venta')) {
            $devolucion->load('venta.impuestos.impuesto');
        } elseif ($devolucion->venta && !$devolucion->venta->relationLoaded('impuestos')) {
            $devolucion->venta->load('impuestos.impuesto');
        }

        $venta = $devolucion->venta;
        if (!$venta || $venta->impuestos->isEmpty()) {
            return collect();
        }

        $baseVenta = (float) $venta->sub_total;
        if ($baseVenta <= 0) {
            return collect();
        }

        $ratio = max(0, min(1, (float) $devolucion->sub_total / $baseVenta));

        return $venta->impuestos
            ->filter(fn ($vi) => (float) $vi->monto > 0)
            ->map(function ($vi) use ($ratio) {
                return (object) [
                    'monto' => round((float) $vi->monto * $ratio, 2),
                    'impuesto' => $vi->impuesto,
                ];
            })
            ->filter(fn ($vi) => (float) $vi->monto > 0)
            ->values();
    }

    /**
     * Resumen DTE (CCF / notas): un registro por impuesto con código MH, descripción y monto.
     */
    protected function buildTributosResumen(): ?Collection
    {
        $doc = $this->documentoFiscal();
        $filas = $this->filasImpuestosDocumento();

        if ($filas->isEmpty()) {
            if ((float) $doc->iva > 0) {
                return collect([[
                    'codigo' => '20',
                    'descripcion' => 'Impuesto al Valor Agregado 13%',
                    'valor' => floatval(number_format($doc->iva, 2, '.', '')),
                ]]);
            }

            return null;
        }

        $tributos = $filas->map(function ($vi) {
            $impuesto = $vi->impuesto;
            $codigo = $this->resolverCodigoMhImpuesto($impuesto);

            if (!$codigo) {
                return null;
            }

            return [
                'codigo' => $codigo,
                'descripcion' => $impuesto ? $impuesto->nombre : 'Impuesto',
                'valor' => floatval(number_format($vi->monto, 2, '.', '')),
            ];
        })->filter()->values();

        if ($tributos->isEmpty() && (float) $doc->iva > 0) {
            return collect([[
                'codigo' => '20',
                'descripcion' => 'Impuesto al Valor Agregado 13%',
                'valor' => floatval(number_format($doc->iva, 2, '.', '')),
            ]]);
        }

        return $tributos->isEmpty() ? null : $tributos;
    }

    /**
     * Códigos MH aplicables a una línea gravada (cuerpoDocumento.tributos).
     *
     * @return string[]|null
     */
    protected function buildTributosLineaCodes($detalle): ?array
    {
        $doc = $this->documentoFiscal();

        if ((float) $doc->iva <= 0 && (float) ($detalle->iva ?? 0) <= 0) {
            return null;
        }

        if ($detalle->producto) {
            if (!$detalle->producto->relationLoaded('impuestos')) {
                $detalle->producto->load('impuestos');
            }

            $codigos = $detalle->producto->impuestos
                ->map(fn ($imp) => $this->resolverCodigoMhImpuesto($imp))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (count($codigos) > 0) {
                return $codigos;
            }
        }

        return $this->buildTributosLineaCodesDesdeDocumento();
    }

    /**
     * @return string[]|null
     */
    protected function buildTributosLineaCodesDesdeDocumento(): ?array
    {
        $doc = $this->documentoFiscal();

        $codigos = $this->filasImpuestosDocumento()
            ->map(fn ($vi) => $this->resolverCodigoMhImpuesto($vi->impuesto))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($codigos) === 0 && (float) $doc->iva > 0) {
            return ['20'];
        }

        return count($codigos) > 0 ? $codigos : null;
    }

    /** @deprecated Use buildTributosLineaCodesDesdeDocumento() */
    protected function buildTributosLineaCodesDesdeVenta(): ?array
    {
        return $this->buildTributosLineaCodesDesdeDocumento();
    }

    /** Monto de IVA (código 20) a nivel documento — para totalIva / ivaItem en factura consumidor. */
    protected function montoIvaVenta(): float
    {
        return $this->montoIvaDocumento();
    }

    protected function montoIvaDocumento(): float
    {
        $filaIva = $this->filasImpuestosDocumento()->first(function ($vi) {
            $imp = $vi->impuesto;
            if (!$imp) {
                return false;
            }

            return $imp->codigo_mh === '20' || ((float) $imp->porcentaje === 13.0 && empty($imp->codigo_mh));
        });

        if ($filaIva) {
            return (float) $filaIva->monto;
        }

        return (float) $this->documentoFiscal()->iva;
    }

    protected function tasaImpuestosDetalle($detalle): float
    {
        if ((float) ($detalle->porcentaje_impuesto ?? 0) > 0) {
            return (float) $detalle->porcentaje_impuesto;
        }

        if ($detalle->producto) {
            if (!$detalle->producto->relationLoaded('impuestos')) {
                $detalle->producto->load('impuestos');
            }

            $suma = (float) $detalle->producto->impuestos->sum('porcentaje');
            if ($suma > 0) {
                return $suma;
            }
        }

        return 13.0;
    }

    protected function tasaIvaDetalle($detalle): float
    {
        if ($detalle->producto) {
            if (!$detalle->producto->relationLoaded('impuestos')) {
                $detalle->producto->load('impuestos');
            }

            $iva = $detalle->producto->impuestos->first(function ($imp) {
                return $imp->codigo_mh === '20' || (float) $imp->porcentaje === 13.0;
            });

            if ($iva) {
                return (float) $iva->porcentaje;
            }
        }

        return 13.0;
    }

    protected function calcularIvaItemFactura($detalle, float $ventaItem): float
    {
        if ($ventaItem <= 0) {
            return 0;
        }

        $doc = $this->documentoFiscal();

        if ((float) ($detalle->iva ?? 0) > 0 && (float) $doc->iva > 0) {
            $montoIvaDoc = $this->montoIvaDocumento();
            $ratio = $montoIvaDoc / (float) $doc->iva;

            return round((float) $detalle->iva * $ratio, 4);
        }

        $tasaTotal = $this->tasaImpuestosDetalle($detalle);
        $tasaIva = $this->tasaIvaDetalle($detalle);

        if ($tasaTotal <= 0) {
            return 0;
        }

        return round($ventaItem * ($tasaIva / 100) / (1 + $tasaTotal / 100), 4);
    }

    protected function factorImpuestosIncluidos($detalle): float
    {
        return 1 + ($this->tasaImpuestosDetalle($detalle) / 100);
    }

    protected function resolverCodigoMhImpuesto($impuesto): ?string
    {
        if (!$impuesto) {
            return null;
        }

        if (!empty($impuesto->codigo_mh)) {
            return $impuesto->codigo_mh;
        }

        if ((float) $impuesto->porcentaje === 13.0) {
            return '20';
        }

        return null;
    }
}
