<?php

namespace App\Services\Contabilidad\CostaRica;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Venta;
use Carbon\Carbon;

/**
 * Formato libro fiscal CR alineado con plantillas Reporte_Detalle_IVA (ventas) y Reporte_Detalle_IVA_Compras.
 * Los montos provienen del JSON del comprobante DGT guardado en {@see Venta::$dte} / {@see Compra::$dte} (`documento`).
 */
final class ReporteDetalleIvaCrService
{
    /** @return array<int, array<string, mixed>> */
    public function filasVentas(string $inicio, string $fin, ?int $idSucursal): array
    {
        $q = Venta::query()
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
            $row = $this->filaDesdeModeloCr($venta->dte, $venta, 1, false);
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
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha')
            ->orderBy('id');
        if ($idSucursal) {
            $qc->where('id_sucursal', $idSucursal);
        }
        foreach ($qc->cursor() as $compra) {
            $row = $this->filaDesdeModeloCr($compra->dte, $compra, 1, true);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $qg = Gasto::query()
            ->where('estado', '!=', 'Cancelado')
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$inicio, $fin])
            ->orderBy('fecha')
            ->orderBy('id');
        if ($idSucursal) {
            $qg->where('id_sucursal', $idSucursal);
        }
        foreach ($qg->cursor() as $gasto) {
            $row = $this->filaDesdeModeloCr($gasto->dte, $gasto, 1, true);
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
     * @param  Venta|Compra|Gasto  $model
     */
    private function filaDesdeModeloCr(?array $dte, $model, int $signe, bool $libroCompras): ?array
    {
        if (! is_array($dte) || ($dte['pais'] ?? '') !== 'CR') {
            return null;
        }
        $doc = $dte['documento'] ?? null;
        if (! is_array($doc) || $doc === []) {
            return null;
        }

        return $this->mapearDocumento($doc, $model, $signe, true, $libroCompras);
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
        if ($tipoNombre === '' && is_object($model) && property_exists($model, 'tipo_documento')) {
            $tipoNombre = (string) ($model->tipo_documento ?? '');
        }

        $dist = $this->distribuirResumen($summary, $signe);

        $exFe = null;
        if (is_object($model) && method_exists($model, 'getAttribute')) {
            $exFe = $model->getAttribute('fe_cr_exoneracion');
        }
        $exonArr = is_array($exFe) ? $exFe : [];
        $tieneExo = ! empty($exonArr['aplica']);
        $exoPorc = isset($exonArr['tarifa_exonerada']) ? (float) $exonArr['tarifa_exonerada'] : '';

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
            'subtotal_exonerado' => 0.0,
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
