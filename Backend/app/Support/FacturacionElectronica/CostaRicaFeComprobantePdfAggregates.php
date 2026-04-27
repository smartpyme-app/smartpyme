<?php

namespace App\Support\FacturacionElectronica;

use Luecano\NumeroALetras\NumeroALetras;

/**
 * Prepara datos de presentación para la representación gráfica PDF FE-CR:
 * líneas agrupadas por tarifa IVA, subtotales por grupo, tabla resumen por impuesto y total en letras.
 */
final class CostaRicaFeComprobantePdfAggregates
{
    /**
     * @return array{
     *     line_groups: list<array{
     *         sort_key: float,
     *         group_key: string,
     *         subtotal_row_label: string,
     *         tax_table_label: string,
     *         lines: list<array{idx: int, line: array<string, mixed>}>
     *     }>,
     *     tax_table_rows: list<array{label: string, base: float, tax: float}>,
     *     total_en_letras: string,
     *     clave_formateada: string
     * }
     */
    public static function fromDocument(array $documento, string $clave, string $monedaCod): array
    {
        $lines = $documento['line_items'] ?? [];
        if (! is_array($lines)) {
            $lines = [];
        }
        $sum = $documento['summary'] ?? [];
        if (! is_array($sum)) {
            $sum = [];
        }

        $buckets = [];
        $rowNum = 0;
        foreach ($lines as $i => $line) {
            if (! is_array($line)) {
                continue;
            }
            $rowNum++;
            $meta = self::lineIvaMeta($line);
            $key = $meta['group_key'];
            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'sort_key' => $meta['sort_key'],
                    'group_key' => $key,
                    'subtotal_row_label' => $meta['subtotal_row_label'],
                    'tax_table_label' => $meta['tax_table_label'],
                    'lines' => [],
                ];
            }
            $buckets[$key]['lines'][] = ['idx' => $rowNum, 'line' => $line];
        }

        uasort($buckets, static function (array $a, array $b): int {
            return $a['sort_key'] <=> $b['sort_key'];
        });

        $lineGroups = [];
        $taxTableRows = [];
        foreach ($buckets as $bucket) {
            $subNet = 0.0;
            $subTax = 0.0;
            $subTot = 0.0;
            foreach ($bucket['lines'] as $entry) {
                $ln = $entry['line'];
                $subNet += (float) ($ln['sub_total'] ?? $ln['taxable_base'] ?? 0);
                $subTax += (float) ($ln['total_tax'] ?? 0);
                $subTot += (float) ($ln['total'] ?? 0);
            }
            $subNet = round($subNet, 2);
            $subTax = round($subTax, 2);
            $subTot = round($subTot, 2);

            $lineGroups[] = array_merge($bucket, [
                'sub_net' => $subNet,
                'sub_tax' => $subTax,
                'sub_total' => $subTot,
            ]);
            $taxTableRows[] = [
                'label' => $bucket['tax_table_label'],
                'base' => $subNet,
                'tax' => $subTax,
            ];
        }

        $total = (float) ($sum['total'] ?? 0);
        $totalEnLetras = self::montoEnLetrasCr($total, $monedaCod);

        return [
            'line_groups' => $lineGroups,
            'tax_table_rows' => $taxTableRows,
            'total_en_letras' => $totalEnLetras,
            'clave_formateada' => self::formatearClave($clave),
        ];
    }

    /**
     * @return array{group_key: string, sort_key: float, subtotal_row_label: string, tax_table_label: string}
     */
    private static function lineIvaMeta(array $line): array
    {
        $sub = (float) ($line['sub_total'] ?? $line['taxable_base'] ?? 0);
        $tax = (float) ($line['total_tax'] ?? 0);
        $ivaTax = self::firstIvaTax($line);
        $rate = (float) ($ivaTax['rate'] ?? 0);
        $ivaType = str_pad(preg_replace('/\D/', '', (string) ($ivaTax['iva_type'] ?? '')), 2, '0', STR_PAD_LEFT);

        if ($tax > 1e-5 && $sub > 1e-5 && $rate < 1e-5) {
            $rate = round(100.0 * $tax / $sub, 4);
        }

        $esExenta = $ivaType === '10' || $ivaType === '11' || $ivaType === '12';

        if ($tax > 1e-5) {
            $rateKey = number_format(round($rate, 2), 2, '.', '');

            return [
                'group_key' => 't_'.$rateKey,
                'sort_key' => (float) $rateKey,
                'subtotal_row_label' => 'TOTAL POR BASE IMPUESTO '.$rateKey.'%',
                'tax_table_label' => 'Impuesto IVA '.$rateKey.'%',
            ];
        }

        if ($esExenta) {
            return [
                'group_key' => 'exento',
                'sort_key' => 999.0,
                'subtotal_row_label' => 'TOTAL BASE EXENTA',
                'tax_table_label' => 'Exento',
            ];
        }

        $rateKey = number_format(0.0, 2, '.', '');

        return [
            'group_key' => 't_'.$rateKey,
            'sort_key' => 0.0,
            'subtotal_row_label' => 'TOTAL POR BASE IMPUESTO '.$rateKey.'%',
            'tax_table_label' => 'Impuesto IVA '.$rateKey.'%',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function firstIvaTax(array $line): array
    {
        $taxes = $line['taxes'] ?? [];
        if (! is_array($taxes)) {
            return [];
        }
        foreach ($taxes as $t) {
            if (is_array($t) && (string) ($t['tax_type'] ?? '01') === '01') {
                return $t;
            }
        }

        return is_array($taxes[0] ?? null) ? $taxes[0] : [];
    }

    private static function formatearClave(string $clave): string
    {
        $d = preg_replace('/\D/', '', $clave);

        return $d !== '' ? trim(chunk_split($d, 5, ' ')) : $clave;
    }

    private static function montoEnLetrasCr(float $monto, string $monedaCod): string
    {
        $monedaCod = strtoupper($monedaCod);
        $sufijo = $monedaCod === 'USD' ? ' DÓLARES' : ' COLONES';

        try {
            $formatter = new NumeroALetras();

            return trim(mb_strtoupper((string) $formatter->toInvoice($monto, 2, $sufijo)));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
