<?php

namespace App\Services;

use App\Models\Inventario\Producto;

/**
 * Aplica actualizaciones a WooCommerce a partir de una resolución de SKU (producto vs variación).
 */
final class WooCommerceResolvedProductWriter
{
    /**
     * @param array $resolution formato devuelto por WooCommerceSkuResolver::resolveBySku
     */
    public function applyResolution(
        WooCommerceApiClient $client,
        Producto $producto,
        array $productData,
        array $resolution
    ): void {
        if (($resolution['mode'] ?? '') === 'variation') {
            $parentId = (int) $resolution['parent_id'];
            $variationId = (int) $resolution['variation_id'];
            $payload = $this->buildVariationPayload($productData);
            $client->put("products/{$parentId}/variations/{$variationId}", $payload);
            $producto->woocommerce_id = $variationId;
            $producto->woocommerce_parent_id = $parentId;
            $producto->last_woocommerce_sync = now();
            $producto->saveQuietly();

            return;
        }

        if (($resolution['mode'] ?? '') === 'product') {
            $productId = (int) $resolution['product_id'];
            $client->put("products/{$productId}", $productData);
            $producto->woocommerce_id = $productId;
            $producto->woocommerce_parent_id = null;
            $producto->last_woocommerce_sync = now();
            $producto->saveQuietly();

            return;
        }

        throw new \InvalidArgumentException('Resolución WooCommerce inválida: falta mode reconocible');
    }

    public function buildVariationPayload(array $productData): array
    {
        return array_filter([
            'sku' => $productData['sku'] ?? null,
            'regular_price' => $productData['regular_price'] ?? null,
            'price' => $productData['price'] ?? null,
            'manage_stock' => $productData['manage_stock'] ?? true,
            'stock_quantity' => $productData['stock_quantity'] ?? 0,
            'stock_status' => $productData['stock_status'] ?? 'outofstock',
        ], function ($v) {
            return $v !== null;
        });
    }
}
