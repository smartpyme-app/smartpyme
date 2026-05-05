<?php

namespace App\Exports\Contabilidad;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class FlujoEfectivoExport implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $flujo,
        private string $empresaNombre,
        private bool $comparar,
    ) {}

    public function title(): string
    {
        return 'Flujo efectivo';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->empresaNombre];
        $rows[] = ['ESTADO DE FLUJOS DE EFECTIVO (indirecto + conciliación) — USD'];
        $rows[] = ['Período actual: ' . (string) ($this->flujo['periodo_actual']['titulo'] ?? '')];
        if ($this->comparar && ! empty($this->flujo['periodo_anterior']['titulo'] ?? '')) {
            $rows[] = ['Período anterior: ' . (string) $this->flujo['periodo_anterior']['titulo']];
        }
        $rows[] = ['Nota: utilidad inicial = estado de resultados NIIF (estimada). v1 excluye variación utilidad_ejercicio en financiación.'];
        $rows[] = [];

        if ($this->comparar) {
            $rows[] = ['Concepto', 'Actual', 'Anterior', 'Diferencia (act − ant)'];
        } else {
            $rows[] = ['Concepto', 'Importe'];
        }

        $this->appendPeriodBlock($rows, 'ACTIVIDADES OPERATIVAS', $this->flujo['actual'] ?? [], $this->comparar ? ($this->flujo['anterior'] ?? null) : null);
        $this->appendPeriodBlock($rows, 'ACTIVIDADES DE INVERSIÓN', $this->flujo['actual'] ?? [], $this->comparar ? ($this->flujo['anterior'] ?? null) : null, 'inversion');
        $this->appendPeriodBlock($rows, 'ACTIVIDADES DE FINANCIACIÓN', $this->flujo['actual'] ?? [], $this->comparar ? ($this->flujo['anterior'] ?? null) : null, 'financiacion');
        $this->appendEfectivoConciliacion($rows, $this->flujo['actual'] ?? [], $this->comparar ? ($this->flujo['anterior'] ?? null) : null);

        return $rows;
    }

    /**
     * @param  'operacion'|'inversion'|'financiacion'  $block
     */
    private function appendPeriodBlock(array &$rows, string $titulo, array $actual, ?array $anterior, string $block = 'operacion'): void
    {
        $rows[] = [];
        $rows[] = [$titulo];
        $key = $block;
        $a = $actual[$key] ?? null;
        $b = $anterior ? ($anterior[$key] ?? null) : null;

        if (! is_array($a)) {
            return;
        }

        foreach ($a['detalle'] ?? [] as $line) {
            $lab = (string) ($line['etiqueta'] ?? $line['clave'] ?? '');
            $m = (float) ($line['monto'] ?? 0);
            $clave = (string) ($line['clave'] ?? '');
            if ($this->comparar && is_array($b)) {
                $mAnt = $this->findMontoByClave($b['detalle'] ?? [], $clave);
                $rows[] = [$lab, $m, $mAnt, $m - $mAnt];
            } else {
                $rows[] = [$lab, $m];
            }
        }
        $tot = (float) ($a['total'] ?? 0);
        if ($this->comparar && is_array($b)) {
            $totB = (float) ($b['total'] ?? 0);
            $rows[] = ['Total ' . strtolower($titulo), $tot, $totB, $tot - $totB];
        } else {
            $rows[] = ['Total ' . strtolower($titulo), $tot];
        }
    }

    private function appendEfectivoConciliacion(array &$rows, array $actual, ?array $anterior): void
    {
        $rows[] = [];
        $rows[] = ['EFECTIVO Y EQUIVALENTES (línea balance)'];
        $e = $actual['efectivo'] ?? [];
        if ($this->comparar && is_array($anterior)) {
            $e2 = $anterior['efectivo'] ?? [];
            $rows[] = ['Saldo línea (inicio periodo)', $e['saldo_linea_inicio'] ?? 0, $e2['saldo_linea_inicio'] ?? 0, ($e['saldo_linea_inicio'] ?? 0) - ($e2['saldo_linea_inicio'] ?? 0)];
            $rows[] = ['Saldo línea (fin periodo)', $e['saldo_linea_fin'] ?? 0, $e2['saldo_linea_fin'] ?? 0, ($e['saldo_linea_fin'] ?? 0) - ($e2['saldo_linea_fin'] ?? 0)];
            $rows[] = ['Variación línea efectivo', $e['variacion_linea'] ?? 0, $e2['variacion_linea'] ?? 0, ($e['variacion_linea'] ?? 0) - ($e2['variacion_linea'] ?? 0)];
        } else {
            $rows[] = ['Saldo línea (inicio periodo)', $e['saldo_linea_inicio'] ?? 0];
            $rows[] = ['Saldo línea (fin periodo)', $e['saldo_linea_fin'] ?? 0];
            $rows[] = ['Variación línea efectivo', $e['variacion_linea'] ?? 0];
        }

        $rows[] = [];
        $rows[] = ['CONCILIACIÓN'];
        $c = $actual['conciliacion'] ?? [];
        if ($this->comparar && is_array($anterior)) {
            $c2 = $anterior['conciliacion'] ?? [];
            $rows[] = ['Flujo indirecto neto', $c['flujo_indirecto_neto'] ?? 0, $c2['flujo_indirecto_neto'] ?? 0, ($c['flujo_indirecto_neto'] ?? 0) - ($c2['flujo_indirecto_neto'] ?? 0)];
            $rows[] = ['Variación efectivo (balance)', $c['variacion_efectivo_balance'] ?? 0, $c2['variacion_efectivo_balance'] ?? 0, ($c['variacion_efectivo_balance'] ?? 0) - ($c2['variacion_efectivo_balance'] ?? 0)];
            $rows[] = ['Diferencia', $c['diferencia'] ?? 0, $c2['diferencia'] ?? 0, ($c['diferencia'] ?? 0) - ($c2['diferencia'] ?? 0)];
        } else {
            $rows[] = ['Flujo indirecto neto', $c['flujo_indirecto_neto'] ?? 0];
            $rows[] = ['Variación efectivo (balance)', $c['variacion_efectivo_balance'] ?? 0];
            $rows[] = ['Diferencia', $c['diferencia'] ?? 0];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     */
    private function findMontoByClave(array $detalle, string $clave): float
    {
        foreach ($detalle as $line) {
            if (($line['clave'] ?? '') === $clave) {
                return (float) ($line['monto'] ?? 0);
            }
        }

        return 0.0;
    }
}
