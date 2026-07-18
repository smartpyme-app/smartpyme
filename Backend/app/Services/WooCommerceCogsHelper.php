<?php

namespace App\Services;

final class WooCommerceCogsHelper
{
    /**
     * Extrae el costo desde el payload REST/webhook de WooCommerce (COGS nativo o legacy).
     */
    public static function extractCostFromPayload(array $payload): ?float
    {
        if (isset($payload['cost_of_goods_sold']) && is_array($payload['cost_of_goods_sold'])) {
            $cogs = $payload['cost_of_goods_sold'];

            if (array_key_exists('total_value', $cogs) && $cogs['total_value'] !== null && $cogs['total_value'] !== '') {
                return max(0, (float) $cogs['total_value']);
            }

            if (array_key_exists('value', $cogs) && $cogs['value'] !== null && $cogs['value'] !== '') {
                return max(0, (float) $cogs['value']);
            }

            if (!empty($cogs['values']) && is_array($cogs['values'])) {
                foreach ($cogs['values'] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    if (array_key_exists('defined_value', $entry) && $entry['defined_value'] !== null && $entry['defined_value'] !== '') {
                        return max(0, (float) $entry['defined_value']);
                    }

                    if (array_key_exists('effective_value', $entry) && $entry['effective_value'] !== null && $entry['effective_value'] !== '') {
                        return max(0, (float) $entry['effective_value']);
                    }
                }
            }
        }

        if (array_key_exists('cogs_value', $payload) && $payload['cogs_value'] !== null && $payload['cogs_value'] !== '') {
            return max(0, (float) $payload['cogs_value']);
        }

        return null;
    }

    /**
     * Arma el bloque cost_of_goods_sold para PUT/POST en la REST API de WooCommerce.
     */
    public static function buildCostPayload(float $costo): array
    {
        return [
            'cost_of_goods_sold' => [
                'values' => [
                    ['defined_value' => round($costo, 2)],
                ],
            ],
        ];
    }

    public static function mergeCostIntoProductData(array $productData, float $costo): array
    {
        if ($costo < 0) {
            return $productData;
        }

        return array_merge($productData, self::buildCostPayload($costo));
    }
}
