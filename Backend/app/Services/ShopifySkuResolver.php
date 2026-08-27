<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Resuelve un SKU canónico (shopify_sku) contra Shopify y ayuda a asignar
 * SKUs únicos para cumplir el requisito de SKU único por variante.
 *
 * Espejo del patrón usado en WooCommerceSkuResolver.
 */
class ShopifySkuResolver
{
    private const PAGE_SIZE = 250;

    private const MAX_PAGES = 100;

    /**
     * Normaliza un SKU para comparación/canonicalización.
     */
    public static function normalizar(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/', ' ', $sku);
        $sku = preg_replace('/[^A-Z0-9_\-]/', '-', $sku);

        return trim($sku, '-');
    }

    /**
     * Asigna un SKU único (shopify_sku) a partir del SKU del proveedor.
     *
     * @param array<string,bool> $usados mapa de shopify_sku ya usados (por referencia)
     */
    public static function asignarShopifySku(string $codigo, $productId, $variantId, array &$usados): string
    {
        $base = self::normalizar($codigo);
        if ($base === '') {
            $base = "{$productId}-{$variantId}";
        }

        $candidato = $base;
        $n = 1;
        while (isset($usados[$candidato])) {
            $candidato = $base . '-' . $n;
            $n++;
        }
        $usados[$candidato] = true;

        return $candidato;
    }

    /**
     * Asigna un SKU único consultando la base de datos (usado en la capa de escritura).
     * Garantiza unicidad por empresa; sufija "-n" ante colisiones.
     */
    public static function resolverSkuUnico(string $codigo, $productId, $variantId, int $empresaId, $excludeId = null): string
    {
        $base = self::normalizar($codigo);
        if ($base === '') {
            $base = "{$productId}-{$variantId}";
        }

        $candidato = $base;
        $n = 1;
        while (self::skuExiste($candidato, $empresaId, $excludeId)) {
            $candidato = $base . '-' . $n;
            $n++;
        }

        return $candidato;
    }

    private static function skuExiste(string $sku, int $empresaId, $excludeId = null): bool
    {
        return \App\Models\Inventario\Producto::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->where('shopify_sku', $sku)
            ->when($excludeId !== null, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->exists();
    }

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
