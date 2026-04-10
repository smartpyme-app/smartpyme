<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Resuelve un SKU de SmartPyme contra WooCommerce: producto simple u otra fila del listado,
 * o variación bajo un producto variable (la API no siempre devuelve variaciones en GET /products?sku=).
 */
final class WooCommerceSkuResolver
{
    private const PAGE_SIZE = 100;

    private const MAX_VARIABLE_PARENT_PAGES = 100;

    private const MAX_VARIATION_PAGES_PER_PARENT = 50;

    /**
     * @return array|null { mode: 'product', product_id: int } | { mode: 'variation', parent_id: int, variation_id: int }
     */
    public function resolveBySku(WooCommerceApiClient $client, string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $fromIndex = $this->findInProductsIndex($client, $sku);
        if ($fromIndex !== null) {
            return $fromIndex;
        }

        return $this->findVariationUnderVariableParents($client, $sku);
    }

    /**
     * @return array|null
     */
    private function findInProductsIndex(WooCommerceApiClient $client, string $sku): ?array
    {
        try {
            $response = $client->get('products', [
                'sku' => $sku,
                'per_page' => 20,
            ]);
        } catch (\Exception $e) {
            Log::warning('WooCommerceSkuResolver: error en búsqueda por SKU en products', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $rows = $response['body'] ?? null;
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }

        foreach ($rows as $row) {
            if (!isset($row['id'])) {
                continue;
            }
            if (($row['sku'] ?? '') !== $sku) {
                continue;
            }

            $type = strtolower((string) ($row['type'] ?? 'simple'));

            if ($type === 'variable') {
                continue;
            }

            if ($type === 'variation' && !empty($row['parent_id'])) {
                return [
                    'mode' => 'variation',
                    'parent_id' => (int) $row['parent_id'],
                    'variation_id' => (int) $row['id'],
                ];
            }

            return [
                'mode' => 'product',
                'product_id' => (int) $row['id'],
            ];
        }

        return null;
    }

    /**
     * @return array|null
     */
    private function findVariationUnderVariableParents(WooCommerceApiClient $client, string $sku): ?array
    {
        $page = 1;

        try {
            while ($page <= self::MAX_VARIABLE_PARENT_PAGES) {
                $response = $client->get('products', [
                    'type' => 'variable',
                    'per_page' => self::PAGE_SIZE,
                    'page' => $page,
                ]);

                $parents = $response['body'] ?? null;
                if (!is_array($parents) || count($parents) === 0) {
                    break;
                }

                foreach ($parents as $parent) {
                    $parentId = (int) ($parent['id'] ?? 0);
                    if ($parentId === 0) {
                        continue;
                    }
                    $found = $this->findVariationInParent($client, $parentId, $sku);
                    if ($found !== null) {
                        return $found;
                    }
                }

                if (count($parents) < self::PAGE_SIZE) {
                    break;
                }
                $page++;
            }
        } catch (\Exception $e) {
            Log::warning('WooCommerceSkuResolver: error buscando variaciones', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @return array|null
     */
    private function findVariationInParent(WooCommerceApiClient $client, int $parentId, string $sku): ?array
    {
        $vPage = 1;

        do {
            $response = $client->get("products/{$parentId}/variations", [
                'per_page' => self::PAGE_SIZE,
                'page' => $vPage,
            ]);

            $variations = $response['body'] ?? null;
            if (!is_array($variations) || count($variations) === 0) {
                break;
            }

            foreach ($variations as $var) {
                if (($var['sku'] ?? '') === $sku && isset($var['id'])) {
                    return [
                        'mode' => 'variation',
                        'parent_id' => $parentId,
                        'variation_id' => (int) $var['id'],
                    ];
                }
            }

            if (count($variations) < self::PAGE_SIZE) {
                break;
            }
            $vPage++;
        } while ($vPage <= self::MAX_VARIATION_PAGES_PER_PARENT);

        return null;
    }
}
