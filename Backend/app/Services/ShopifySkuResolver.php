<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Resuelve un SKU contra Shopify (busca la variante que posee ese SKU).
 *
 * El SKU de Shopify se guarda tal cual en `productos.codigo` y `productos.shopify_sku`.
 * Si el SKU viene vacío desde Shopify, se deja vacío y se completa manualmente o
 * re-sincronizando desde Shopify (no se genera un código sintético).
 *
 * Espejo del patrón usado en WooCommerceSkuResolver.
 */
class ShopifySkuResolver
{
    private const PAGE_SIZE = 250;

    private const MAX_PAGES = 100;

    /**
     * Busca un SKU en Shopify recorriendo las variantes de todos los productos.
     *
     * @return array|null|string
     *   - array { product_id, variant_id, inventory_item_id } si hay exactamente 1 coincidencia
     *   - null si no hay coincidencias
     *   - 'conflict' si hay más de una coincidencia
     */
    public function resolveBySku(ShopifyApiClient $client, string $sku)
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $encontrados = [];

        try {
            $page = 1;
            do {
                $response = $client->get('products.json', [
                    'limit' => self::PAGE_SIZE,
                    'page' => $page,
                    'fields' => 'id,variants',
                ]);

                $products = $response['body']['products'] ?? [];
                if (!is_array($products) || count($products) === 0) {
                    break;
                }

                foreach ($products as $product) {
                    $productId = (int) ($product['id'] ?? 0);
                    foreach ($product['variants'] ?? [] as $variant) {
                        if (($variant['sku'] ?? '') === $sku) {
                            $encontrados[] = [
                                'product_id' => $productId,
                                'variant_id' => (int) ($variant['id'] ?? 0),
                                'inventory_item_id' => (int) ($variant['inventory_item_id'] ?? 0),
                            ];
                        }
                    }
                }

                // Si ya hay > 1, no hace falta seguir paginando.
                if (count($encontrados) > 1) {
                    return 'conflict';
                }

                if (count($products) < self::PAGE_SIZE) {
                    break;
                }
                $page++;
            } while ($page <= self::MAX_PAGES);
        } catch (\Exception $e) {
            Log::warning('ShopifySkuResolver: error buscando por SKU', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (count($encontrados) === 1) {
            return $encontrados[0];
        }

        if (count($encontrados) > 1) {
            return 'conflict';
        }

        return null;
    }
}
