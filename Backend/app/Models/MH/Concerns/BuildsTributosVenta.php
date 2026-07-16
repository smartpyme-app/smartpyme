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
        $filas = $this->filasImpuestosDocumento();

        if ($filas->isEmpty()) {
            if ($this->documentoTieneIva()) {
                return collect([[
                    'codigo' => '20',
                    'descripcion' => 'Impuesto al Valor Agregado 13%',
                    'valor' => floatval(number_format($this->montoIvaDocumento(), 2, '.', '')),
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

        if ($tributos->isEmpty() && $this->documentoTieneIva()) {
            return collect([[
                'codigo' => '20',
                'descripcion' => 'Impuesto al Valor Agregado 13%',
                'valor' => floatval(number_format($this->montoIvaDocumento(), 2, '.', '')),
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
        if (!$this->documentoTieneIva() && !$this->documentoTieneTributosNoIva()) {
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
        $codigos = $this->filasImpuestosDocumento()
            ->map(fn ($vi) => $this->resolverCodigoMhImpuesto($vi->impuesto))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($codigos) === 0 && $this->documentoTieneIva()) {
            return ['20'];
        }

        return count($codigos) > 0 ? $codigos : null;
    }

    /** @deprecated Use buildTributosLineaCodesDesdeDocumento() */
    protected function buildTributosLineaCodesDesdeVenta(): ?array
    {
        return $this->buildTributosLineaCodesDesdeDocumento();
    }

    protected function esImpuestoIva($impuesto): bool
    {
        if (!$impuesto) {
            return false;
        }

        return $impuesto->codigo_mh === '20'
            || ((float) $impuesto->porcentaje === 13.0 && empty($impuesto->codigo_mh));
    }

    /**
     * Factura consumidor (01): cuerpoDocumento.tributos solo lleva tributos distintos al IVA (CAT-015).
     *
     * @return string[]|null
     */
    protected function buildTributosLineaCodesFacturaConsumidor($detalle): ?array
    {
        if (!$this->documentoTieneIva() && !$this->documentoTieneTributosNoIva()) {
            return null;
        }

        if ($detalle->producto) {
            if (!$detalle->producto->relationLoaded('impuestos')) {
                $detalle->producto->load('impuestos');
            }

            $codigos = $detalle->producto->impuestos
                ->filter(fn ($imp) => !$this->esImpuestoIva($imp))
                ->map(fn ($imp) => $this->resolverCodigoMhImpuesto($imp))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (count($codigos) > 0) {
                return $codigos;
            }
        }

        return $this->buildTributosLineaCodesFacturaConsumidorDesdeDocumento();
    }

    /**
     * Códigos MH (no IVA) a nivel documento — p. ej. descripción personalizada sin producto.
     *
     * @return string[]|null
     */
    protected function buildTributosLineaCodesFacturaConsumidorDesdeDocumento(): ?array
    {
        $codigos = $this->filasImpuestosDocumento()
            ->filter(fn ($vi) => !$this->esImpuestoIva($vi->impuesto))
            ->map(fn ($vi) => $this->resolverCodigoMhImpuesto($vi->impuesto))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return count($codigos) > 0 ? $codigos : null;
    }

    /**
     * Factura consumidor (01): resumen.tributos con montos de gravámenes distintos al IVA.
     * Obligatorio cuando cuerpoDocumento[].tributos lleva códigos no-IVA.
     */
    protected function buildTributosResumenFacturaConsumidor(): ?Collection
    {
        $tributos = $this->filasImpuestosDocumento()
            ->filter(fn ($vi) => !$this->esImpuestoIva($vi->impuesto))
            ->map(function ($vi) {
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
            })
            ->filter()
            ->values();

        return $tributos->isEmpty() ? null : $tributos;
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

    /**
     * IVA por línea en factura consumidor: el MH valida ventaGravada × 13 / 113.
     */
    protected function calcularIvaItemFactura($detalle, float $ventaItem): float
    {
        if (!$this->documentoTieneIva()) {
            return 0.0;
        }

        return $this->calcularIvaItemFacturaConsumidor($ventaItem);
    }

    protected function calcularIvaItemFacturaConsumidor(float $ventaGravada): float
    {
        if ($ventaGravada <= 0) {
            return 0;
        }

        return round($ventaGravada * 13 / 113, 4);
    }

    protected function factorImpuestosIncluidos($detalle): float
    {
        return 1 + ($this->tasaImpuestosDetalle($detalle) / 100);
    }

    /** Solo IVA embebido en ventaGravada; otros tributos van en resumen.tributos. */
    protected function factorIvaIncluidoDetalle($detalle): float
    {
        return 1 + ($this->tasaIvaDetalle($detalle) / 100);
    }

    protected function montoTributosNoIvaDocumento(): float
    {
        return round((float) $this->filasImpuestosDocumento()
            ->filter(fn ($vi) => !$this->esImpuestoIva($vi->impuesto))
            ->sum(fn ($vi) => (float) $vi->monto), 2);
    }

    protected function documentoTieneIva(): bool
    {
        return $this->montoIvaDocumento() > 0;
    }

    protected function documentoTieneTributosNoIva(): bool
    {
        return $this->montoTributosNoIvaDocumento() > 0;
    }

    /** CCF / notas: subTotal neto + suma de resumen.tributos[].valor */
    protected function sumaValoresTributosResumen(?Collection $tributos): float
    {
        if (!$tributos || $tributos->isEmpty()) {
            return 0.0;
        }

        return round((float) $tributos->sum(fn ($t) => (float) ($t['valor'] ?? 0)), 2);
    }

    protected function montoTotalOperacionConTributos(float $subTotal, ?Collection $tributosResumen, float $totalDescu = 0): float
    {
        return floatval(number_format(
            $subTotal - $totalDescu + $this->sumaValoresTributosResumen($tributosResumen),
            2,
            '.',
            ''
        ));
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
