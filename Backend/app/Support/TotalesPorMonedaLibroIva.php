<?php

namespace App\Support;

/**
 * Totales nativos y equivalentes agrupados por currency_code (resumen IVA en pantalla).
 */
final class TotalesPorMonedaLibroIva
{
    /**
     * @param  iterable<object>  $documentos
     * @param  iterable<object>  $devoluciones  Restan nativo/equivalente; no suman a documentos.
     * @return list<array{moneda: string, documentos: int, total_nativo: float, total_equivalente: float}>
     */
    public static function agrupar(iterable $documentos, iterable $devoluciones = []): array
    {
        /** @var array<string, array{moneda: string, documentos: int, total_nativo: float, total_equivalente: float}> $acc */
        $acc = [];

        foreach ($documentos as $doc) {
            $moneda = self::moneda($doc);
            $row = $acc[$moneda] ?? [
                'moneda' => $moneda,
                'documentos' => 0,
                'total_nativo' => 0.0,
                'total_equivalente' => 0.0,
            ];
            $row['documentos']++;
            $row['total_nativo'] += self::nativo($doc);
            $row['total_equivalente'] += self::equivalente($doc);
            $acc[$moneda] = $row;
        }

        foreach ($devoluciones as $dev) {
            $moneda = self::moneda($dev);
            $nativo = self::nativo($dev);
            $eq = self::equivalente($dev);
            $row = $acc[$moneda] ?? [
                'moneda' => $moneda,
                'documentos' => 0,
                'total_nativo' => 0.0,
                'total_equivalente' => 0.0,
            ];
            $row['total_nativo'] -= $nativo > 0 ? $nativo : -$nativo;
            $row['total_equivalente'] -= $eq > 0 ? $eq : -$eq;
            $acc[$moneda] = $row;
        }

        $rows = array_values($acc);
        usort($rows, function (array $a, array $b): int {
            $order = ['CRC' => 0, 'USD' => 1];

            return ($order[$a['moneda']] ?? 9) <=> ($order[$b['moneda']] ?? 9)
                ?: strcmp($a['moneda'], $b['moneda']);
        });

        foreach ($rows as &$row) {
            $row['total_nativo'] = round($row['total_nativo'], 2);
            $row['total_equivalente'] = round($row['total_equivalente'], 2);
        }
        unset($row);

        return array_values(array_filter(
            $rows,
            fn (array $r) => $r['documentos'] !== 0 || abs($r['total_nativo']) > 0.00001 || abs($r['total_equivalente']) > 0.00001
        ));
    }

    private static function moneda(object $model): string
    {
        $c = strtoupper(trim((string) ($model->currency_code ?? '')));

        return $c !== '' ? $c : 'CRC';
    }

    private static function nativo(object $model): float
    {
        return (float) ($model->total ?? 0);
    }

    private static function equivalente(object $model): float
    {
        $eq = $model->equivalent_total ?? null;
        if ($eq !== null && $eq !== '') {
            return (float) $eq;
        }

        $nativo = self::nativo($model);
        if (self::moneda($model) !== 'USD') {
            return $nativo;
        }

        $rate = (float) ($model->exchange_rate ?? 0);

        return $nativo * ($rate > 0.0 ? $rate : 1.0);
    }
}
