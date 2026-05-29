<?php

namespace App\Services\Contabilidad\CostaRica;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Venta;
use Carbon\Carbon;

/**
 * Formato libro fiscal CR alineado con plantillas Reporte_Detalle_IVA (ventas) y Reporte_Detalle_IVA_Compras.
 * Montos desde tablas del registro (ventas/venta_impuestos, compras, egresos/detalles); sin leer DTE.
 */
final class ReporteDetalleIvaCrService
{
    /** @return array<int, array<string, mixed>> */
    public function filasVentas(string $inicio, string $fin, ?int $idSucursal): array
    {
        $q = Venta::query()
            ->with(['cliente', 'documento', 'empresa', 'impuestos.impuesto', 'detalles'])
            ->withCount('detalles')
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha')
            ->orderBy('id');

        if ($idSucursal) {
            $q->where('id_sucursal', $idSucursal);
        }

        $rows = [];
        foreach ($q->cursor() as $venta) {
            $row = $this->filaDesdeVenta($venta);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, function (array $a, array $b) {
            $fa = $a['fecha_iso'] ?? '';
            $fb = $b['fecha_iso'] ?? '';
            if ($fa !== $fb) {
                return strcmp((string) $fa, (string) $fb);
            }

            return strcmp((string) ($a['clave'] ?? ''), (string) ($b['clave'] ?? ''));
        });

        foreach ($rows as $i => $_) {
            unset($rows[$i]['fecha_iso']);
        }

        return array_values($rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function filasCompras(string $inicio, string $fin, ?int $idSucursal): array
    {
        $rows = [];

        $qc = Compra::query()
            ->with(['proveedor', 'empresa'])
            ->withCount('detalles')
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha')
            ->orderBy('id');
        if ($idSucursal) {
            $qc->where('id_sucursal', $idSucursal);
        }
        foreach ($qc->cursor() as $compra) {
            $row = $this->filaDesdeCompra($compra);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $qg = Gasto::query()
            ->with(['proveedor', 'empresa', 'detalles'])
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha')
            ->orderBy('id');
        if ($idSucursal) {
            $qg->where('id_sucursal', $idSucursal);
        }
        foreach ($qg->cursor() as $gasto) {
            $row = $this->filaDesdeGasto($gasto);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        usort($rows, function (array $a, array $b) {
            $fa = $a['fecha_iso'] ?? '';
            $fb = $b['fecha_iso'] ?? '';
            if ($fa !== $fb) {
                return strcmp((string) $fa, (string) $fb);
            }

            return strcmp((string) ($a['clave'] ?? ''), (string) ($b['clave'] ?? ''));
        });

        foreach ($rows as $i => $_) {
            unset($rows[$i]['fecha_iso']);
        }

        return array_values($rows);
    }

    /**
     * Fila del reporte detalle IVA ventas desde tablas ventas / venta_impuestos (mismo criterio que resumen-fiscal).
     */
    private function filaDesdeVenta(Venta $venta): ?array
    {
        return $this->mapearDocumento($this->documentoDesdeVenta($venta), $venta, 1, true, false);
    }

    private function filaDesdeCompra(Compra $compra): ?array
    {
        return $this->mapearDocumento($this->documentoDesdeCompra($compra), $compra, 1, true, true);
    }

    private function filaDesdeGasto(Gasto $gasto): ?array
    {
        return $this->mapearDocumento($this->documentoDesdeGasto($gasto), $gasto, 1, true, true);
    }

    /** @return array<string, mixed> */
    private function documentoDesdeVenta(Venta $venta): array
    {
        $empresa = $venta->empresa;
        [$nombreReceptor, $idReceptor] = $this->datosTercero($venta->cliente, 'Consumidor final');

        $lineCount = (int) ($venta->detalles_count ?? 0);

        return [
            'date' => $venta->fecha,
            'sequential' => (string) ($venta->correlativo ?? ''),
            'issuer' => [
                'name' => (string) (optional($empresa)->nombre ?? ''),
                'identification_number' => preg_replace('/\D/', '', (string) (optional($empresa)->nit ?? '')),
            ],
            'receiver' => [
                'name' => $nombreReceptor,
                'identification_number' => $idReceptor,
            ],
            'summary' => $this->resumenDesdeVenta($venta),
            'line_items' => array_fill(0, max(0, $lineCount), []),
            'currency' => [
                'currency_code' => 'CRC',
                'exchange_rate' => 1,
            ],
            'payments' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function documentoDesdeCompra(Compra $compra): array
    {
        $empresa = $compra->empresa;
        $proveedor = $compra->proveedor;
        [$nombreEmisor, $idEmisor] = $this->datosTercero($proveedor, 'Proveedor');

        $folio = trim((string) ($compra->referencia ?? ''));
        if ($folio === '') {
            $folio = trim((string) ($compra->num_serie ?? ''));
        }

        return [
            'date' => $compra->fecha,
            'sequential' => $folio,
            'issuer' => [
                'name' => $nombreEmisor,
                'identification_number' => $idEmisor,
            ],
            'receiver' => [
                'name' => (string) (optional($empresa)->nombre ?? ''),
                'identification_number' => preg_replace('/\D/', '', (string) (optional($empresa)->nit ?? '')),
            ],
            'summary' => $this->resumenDesdeCompra($compra),
            'line_items' => array_fill(0, max(0, (int) ($compra->detalles_count ?? 0)), []),
            'currency' => [
                'currency_code' => 'CRC',
                'exchange_rate' => 1,
            ],
            'payments' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function documentoDesdeGasto(Gasto $gasto): array
    {
        $empresa = $gasto->empresa;
        $proveedor = $gasto->proveedor;
        [$nombreEmisor, $idEmisor] = $this->datosTercero($proveedor, 'Proveedor');

        $folio = trim((string) ($gasto->referencia ?? ''));
        if ($folio === '') {
            $folio = trim((string) ($gasto->num_identificacion ?? ''));
        }

        $lineCount = $gasto->relationLoaded('detalles') ? $gasto->detalles->count() : 0;

        return [
            'date' => $gasto->fecha,
            'sequential' => $folio,
            'issuer' => [
                'name' => $nombreEmisor,
                'identification_number' => $idEmisor,
            ],
            'receiver' => [
                'name' => (string) (optional($empresa)->nombre ?? ''),
                'identification_number' => preg_replace('/\D/', '', (string) (optional($empresa)->nit ?? '')),
            ],
            'summary' => $this->resumenDesdeGasto($gasto),
            'line_items' => array_fill(0, max(0, $lineCount), []),
            'currency' => [
                'currency_code' => 'CRC',
                'exchange_rate' => 1,
            ],
            'payments' => [],
        ];
    }

    /** @return array{0: string, 1: string} */
    private function datosTercero(?object $tercero, string $defectoNombre): array
    {
        if (! $tercero) {
            return [$defectoNombre, '00000000000000000000'];
        }
        $nombre = $tercero->tipo === 'Empresa'
            ? (string) ($tercero->nombre_empresa ?? $defectoNombre)
            : trim((string) $tercero->nombre.' '.(string) $tercero->apellido);
        $id = preg_replace('/\D/', '', (string) ($tercero->nit ?? $tercero->ncr ?? $tercero->dui ?? ''));
        if ($id === '') {
            $id = '00000000000000000000';
        }

        return [$nombre !== '' ? $nombre : $defectoNombre, $id];
    }

    /** @return array<string, mixed> */
    private function resumenDesdeVenta(Venta $venta): array
    {
        $impuestos = ($venta->relationLoaded('impuestos') && $venta->impuestos->isNotEmpty())
            ? $venta->impuestos
            : [];

        $summary = $this->resumenDesdeMontos(
            $impuestos,
            (float) $venta->iva,
            (float) $venta->sub_total,
            (float) ($venta->exenta ?? 0),
            (float) ($venta->no_sujeta ?? 0),
            isset($venta->gravada) ? (float) $venta->gravada : null
        );

        if ($venta->relationLoaded('detalles') && $venta->detalles->isNotEmpty()) {
            $exonerado = 0.0;
            foreach ($venta->detalles as $detalle) {
                if (! $this->detalleTieneExoneracionCr($detalle)) {
                    continue;
                }
                $exonerado += (float) ($detalle->sub_total ?? $detalle->gravada ?? 0);
            }
            if ($exonerado > 0.00001) {
                $summary['total_exonerated'] = round($exonerado, 2);
            }
        }

        return $summary;
    }

    private function detalleTieneExoneracionCr(object $detalle): bool
    {
        $ex = null;
        if (method_exists($detalle, 'getAttribute')) {
            $ex = $detalle->getAttribute('fe_cr_exoneracion');
        }
        if (is_array($ex) && ! empty($ex['aplica'])) {
            return true;
        }

        return strtolower(trim((string) ($detalle->tipo_gravado ?? ''))) === 'exonerada';
    }

    private function ventaTieneExoneracionCr(Venta $venta): bool
    {
        $ex = $venta->getAttribute('fe_cr_exoneracion');
        if (is_array($ex) && ! empty($ex['aplica'])) {
            return true;
        }
        if ($venta->relationLoaded('detalles')) {
            foreach ($venta->detalles as $detalle) {
                if ($this->detalleTieneExoneracionCr($detalle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function primeraTarifaExoneracionVentaCr(Venta $venta): float|string
    {
        if ($venta->relationLoaded('detalles')) {
            foreach ($venta->detalles as $detalle) {
                $ex = $detalle->fe_cr_exoneracion;
                if (is_array($ex) && ! empty($ex['aplica']) && isset($ex['tarifa_exonerada'])) {
                    return (float) $ex['tarifa_exonerada'];
                }
            }
        }
        $ex = $venta->fe_cr_exoneracion;
        if (is_array($ex) && isset($ex['tarifa_exonerada'])) {
            return (float) $ex['tarifa_exonerada'];
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function resumenDesdeCompra(Compra $compra): array
    {
        return $this->resumenDesdeMontos(
            [],
            (float) $compra->iva,
            (float) $compra->sub_total,
            (float) ($compra->exenta ?? 0) + (float) ($compra->otros_cargos ?? 0),
            (float) ($compra->no_sujeta ?? 0),
            null
        );
    }

    /** @return array<string, mixed> */
    private function resumenDesdeGasto(Gasto $gasto): array
    {
        $taxes = [];
        if ($gasto->relationLoaded('detalles') && $gasto->detalles->isNotEmpty()) {
            foreach ($gasto->detalles as $detalle) {
                $ivaLine = (float) ($detalle->iva ?? 0);
                if (abs($ivaLine) <= 0.00001) {
                    continue;
                }
                $base = (float) ($detalle->sub_total ?? 0);
                $rate = $base > 0.00001
                    ? round(($ivaLine / $base) * 100, 2)
                    : 13.0;
                $taxes[] = ['rate' => $rate, 'amount' => $ivaLine];
            }
        }

        if ($taxes !== []) {
            $exempt = 0.0;
            $taxed = 0.0;
            if ($gasto->relationLoaded('detalles')) {
                foreach ($gasto->detalles as $detalle) {
                    $sub = (float) ($detalle->sub_total ?? 0);
                    $tipo = strtolower((string) ($detalle->tipo_gravado ?? 'gravada'));
                    if (in_array($tipo, ['exenta', 'no_sujeta', 'no sujeta'], true)) {
                        $exempt += $sub;
                    } else {
                        $taxed += $sub;
                    }
                }
            }

            $otrosCargos = (float) ($gasto->otros_cargos ?? 0);

            return [
                'total_exempt' => $exempt + $otrosCargos,
                'total_taxed' => $taxed,
                'taxes' => $taxes,
            ];
        }

        return $this->resumenDesdeMontos(
            [],
            (float) $gasto->iva,
            (float) $gasto->sub_total,
            (float) ($gasto->otros_cargos ?? 0),
            0.0,
            null
        );
    }

    /**
     * @param  iterable<int, object>  $lineasImpuesto  filas con ->impuesto y ->monto
     * @return array<string, mixed>
     */
    private function resumenDesdeMontos(
        iterable $lineasImpuesto,
        float $iva,
        float $subTotal,
        float $exenta,
        float $noSujeta,
        ?float $gravada
    ): array {
        $taxes = [];
        foreach ($lineasImpuesto as $linea) {
            $rate = (float) (optional($linea->impuesto)->porcentaje ?? 0);
            $amount = (float) ($linea->monto ?? 0);
            if (abs($amount) > 0.00001 || $rate > 0.00001) {
                $taxes[] = ['rate' => $rate, 'amount' => $amount];
            }
        }

        if ($taxes === [] && $iva > 0.00001) {
            $base = ($gravada !== null && $gravada > 0.00001)
                ? $gravada
                : max(0.0, $subTotal - $exenta - $noSujeta);
            if ($base <= 0.00001) {
                $base = $subTotal;
            }
            $rate = $base > 0.00001
                ? round(($iva / $base) * 100, 2)
                : 13.0;
            $taxes[] = ['rate' => $rate, 'amount' => $iva];
        }

        $exempt = $exenta + $noSujeta;
        $taxed = $gravada ?? 0.0;
        if ($taxed <= 0.00001 && $iva > 0.00001) {
            $taxed = max(0.0, $subTotal - $exempt);
        }

        return [
            'total_exempt' => $exempt,
            'total_taxed' => $taxed,
            'taxes' => $taxes,
        ];
    }

    /**
     * @param  Venta|Compra|Gasto  $model
     * @return array<string, mixed>|null
     */
    public function mapearDocumento(array $doc, $model, int $signe, bool $incluirFechaIso = false, bool $libroCompras = false): ?array
    {
        $issuer = is_array($doc['issuer'] ?? null) ? $doc['issuer'] : [];
        $receiver = is_array($doc['receiver'] ?? null) ? $doc['receiver'] : [];
        $summary = is_array($doc['summary'] ?? null) ? $doc['summary'] : [];
        $lineItems = is_array($doc['line_items'] ?? null) ? $doc['line_items'] : [];
        $currency = is_array($doc['currency'] ?? null) ? $doc['currency'] : [];
        $payments = is_array($doc['payments'] ?? null) ? $doc['payments'] : [];

        $fechaEmision = $doc['date'] ?? $model->fecha ?? null;
        try {
            $fechaIso = $fechaEmision ? Carbon::parse($fechaEmision)->format('Y-m-d') : '';
            $fechaDisplay = $fechaEmision ? Carbon::parse($fechaEmision)->format('d/m/Y') : '';
        } catch (\Throwable $e) {
            $fechaIso = '';
            $fechaDisplay = '';
        }

        $tipoNombre = '';
        if (is_object($model) && property_exists($model, 'nombre_documento')) {
            $tipoNombre = (string) ($model->nombre_documento ?? '');
        }
        if ($tipoNombre === '' && is_object($model) && method_exists($model, 'documento')) {
            $tipoNombre = trim((string) (optional($model->documento)->nombre ?? ''));
        }
        if ($tipoNombre === '' && is_object($model) && property_exists($model, 'tipo_documento')) {
            $tipoNombre = trim((string) ($model->tipo_documento ?? ''));
        }
        if ($tipoNombre === '' && $model instanceof Gasto) {
            $tipoNombre = trim((string) ($model->concepto ?? ''));
        }

        $dist = $this->distribuirResumen($summary, $signe);

        $tieneExo = false;
        $exoPorc = '';
        if ($model instanceof Venta) {
            $tieneExo = $this->ventaTieneExoneracionCr($model);
            if ($tieneExo) {
                $exoPorc = $this->primeraTarifaExoneracionVentaCr($model);
            }
        } elseif (is_object($model) && method_exists($model, 'getAttribute')) {
            $exFe = $model->getAttribute('fe_cr_exoneracion');
            $exonArr = is_array($exFe) ? $exFe : [];
            $tieneExo = ! empty($exonArr['aplica']);
            $exoPorc = isset($exonArr['tarifa_exonerada']) ? (float) $exonArr['tarifa_exonerada'] : '';
        }

        $retenciones = 0.0;
        if (is_object($model) && property_exists($model, 'iva_retenido')) {
            $retenciones = round((float) ($model->iva_retenido ?? 0) * $signe, 5);
        }

        $ivaDev = 0.0;
        if (is_object($model) && property_exists($model, 'iva_devuelto')) {
            $ivaDev = round((float) ($model->iva_devuelto ?? 0) * $signe, 5);
        }

        $folio = isset($doc['sequential']) ? (string) $doc['sequential'] : '';
        $clave = '';
        if (is_object($model) && property_exists($model, 'codigo_generacion')) {
            $clave = (string) ($model->codigo_generacion ?? '');
        }

        $row = [
            'nombre_emisor' => (string) ($issuer['name'] ?? ''),
            'rfc_emisor' => preg_replace('/\D/', '', (string) ($issuer['identification_number'] ?? '')),
            'fecha' => $fechaDisplay,
            'nombre_receptor' => (string) ($receiver['name'] ?? ''),
            'rfc_receptor' => preg_replace('/\D/', '', (string) ($receiver['identification_number'] ?? '')),
            'tipo_doc' => $tipoNombre,
            'cant_lineas' => count($lineItems),
            'exoneracion' => $tieneExo ? 'SI' : 'NO',
        ];
        if (! $libroCompras) {
            $row['exo_porc'] = $exoPorc === '' ? '' : round((float) $exoPorc, 2);
        }
        $row['retenciones'] = $retenciones;
        $row['folio'] = $folio;
        $row['clave'] = $clave;
        $row['medio_pago'] = $this->textoMedioPago($payments, $model, $signe);
        $row['cod_moneda'] = strtoupper((string) ($currency['currency_code'] ?? 'CRC'));
        $row['tipo_cambio'] = round((float) ($currency['exchange_rate'] ?? 1), 5);
        $row['subtotal_13'] = $dist['subtotal_13'];
        $row['subtotal_8'] = $dist['subtotal_8'];
        $row['subtotal_4'] = $dist['subtotal_4'];
        $row['subtotal_2'] = $dist['subtotal_2'];
        $row['subtotal_1'] = $dist['subtotal_1'];
        $row['subtotal_exonerado'] = $dist['subtotal_exonerado'];
        $row['subtotal_exento'] = $dist['subtotal_exento'];
        $row['subtotal_gravado'] = $dist['subtotal_gravado'];
        $row['iva_13'] = $dist['iva_13'];
        $row['iva_8'] = $dist['iva_8'];
        $row['iva_4'] = $dist['iva_4'];
        $row['iva_2'] = $dist['iva_2'];
        $row['iva_1'] = $dist['iva_1'];
        $row['iva_devuelto'] = $ivaDev;

        if ($incluirFechaIso) {
            $row['fecha_iso'] = $fechaIso;
        }

        return $row;
    }

    /**
     * @param  Venta|Compra|Gasto  $model
     */
    private function textoMedioPago(array $payments, $model, int $signe): string
    {
        if ($payments !== []) {
            $p0 = $payments[0] ?? [];
            $code = (string) ($p0['payment_method'] ?? $p0['tipo'] ?? '01');
            $monto = isset($p0['amount']) ? round((float) $p0['amount'] * abs($signe), 5) : 0.0;

            return $code.':'.$monto.':'.($signe < 0 ? 'Devolución / ajuste' : 'Pago');
        }
        $total = 0.0;
        if (is_object($model) && property_exists($model, 'total')) {
            $total = round((float) ($model->total ?? 0) * abs($signe), 5);
        }
        $forma = '';
        if (is_object($model) && property_exists($model, 'forma_pago')) {
            $forma = trim((string) ($model->forma_pago ?? ''));
        }
        if ($forma === '') {
            $forma = 'Efectivo';
        }

        return '01:'.$total.':'.$forma;
    }

    /** @return array<string, float> */
    private function distribuirResumen(array $summary, int $signe): array
    {
        $sgn = static fn (float $x): float => round($x * $signe, 5);

        $out = [
            'subtotal_13' => 0.0,
            'subtotal_8' => 0.0,
            'subtotal_4' => 0.0,
            'subtotal_2' => 0.0,
            'subtotal_1' => 0.0,
            'iva_13' => 0.0,
            'iva_8' => 0.0,
            'iva_4' => 0.0,
            'iva_2' => 0.0,
            'iva_1' => 0.0,
            'subtotal_exento' => $sgn((float) ($summary['total_exempt'] ?? 0)),
            'subtotal_gravado' => $sgn((float) ($summary['total_taxed'] ?? 0)),
            'subtotal_exonerado' => $sgn((float) ($summary['total_exonerated'] ?? 0)),
        ];

        foreach ($summary['taxes'] ?? [] as $tax) {
            if (! is_array($tax)) {
                continue;
            }
            $rate = round((float) ($tax['rate'] ?? 0), 2);
            $ivaRaw = (float) ($tax['amount'] ?? 0);
            $ivaSigned = $sgn($ivaRaw);
            if ($ivaSigned === 0.0 && $rate <= 0) {
                continue;
            }
            $bucket = $this->bucketTarifa($rate);
            if ($bucket === null) {
                continue;
            }
            $baseRaw = $rate > 0.0001 ? $ivaRaw / ($rate / 100.0) : 0.0;
            $baseSigned = $sgn($baseRaw);
            $out['subtotal_'.$bucket] += $baseSigned;
            $out['iva_'.$bucket] += $ivaSigned;
        }

        return $out;
    }

    private function bucketTarifa(float $rate): ?string
    {
        foreach ([13, 8, 4, 2, 1] as $r) {
            if (abs($rate - (float) $r) < 0.05) {
                return (string) $r;
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $filas */
    public function totales(array $filas): array
    {
        $keys = [
            'retenciones', 'subtotal_13', 'subtotal_8', 'subtotal_4', 'subtotal_2', 'subtotal_1',
            'subtotal_exonerado', 'subtotal_exento', 'subtotal_gravado',
            'iva_13', 'iva_8', 'iva_4', 'iva_2', 'iva_1', 'iva_devuelto',
        ];
        $sum = array_fill_keys($keys, 0.0);
        foreach ($filas as $f) {
            foreach ($keys as $k) {
                $sum[$k] += (float) ($f[$k] ?? 0);
            }
        }
        foreach ($sum as $k => $v) {
            $sum[$k] = round($v, 5);
        }

        return $sum;
    }
}
