<?php

namespace App\Services\Bonos\Calculators;

class GrupalCalculator implements BonoCalculator
{
    public function calcular(array $config, float $ventas): float
    {
        // ponytail: team split needs ventasPorVendedor; evaluation calls repartir()
        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, float>  $ventasPorVendedor
     * @return array<int, float>
     */
    public function repartir(array $config, array $ventasPorVendedor): array
    {
        $meta = (float) ($config['meta'] ?? 0);
        $bono = (float) ($config['bono'] ?? 0);
        $reparto = $config['reparto'] ?? 'equitativo';
        $total = array_sum($ventasPorVendedor);
        if ($total < $meta) {
            return array_fill_keys(array_keys($ventasPorVendedor), 0.0);
        }
        $ids = array_keys($ventasPorVendedor);
        if ($reparto === 'proporcional') {
            $out = [];
            foreach ($ventasPorVendedor as $id => $v) {
                $out[$id] = $total > 0 ? round($bono * ($v / $total), 4) : 0.0;
            }

            return $out;
        }
        $cada = round($bono / max(1, count($ids)), 4);

        return array_fill_keys($ids, $cada);
    }
}
