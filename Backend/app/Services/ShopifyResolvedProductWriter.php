<?php

namespace App\Services;

use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Log;

/**
 * Aplica actualizaciones a Shopify a partir de una resolución de SKU
 * (resultado de ShopifySkuResolver), evitando crear productos duplicados.
 *
 * Espejo del patrón usado en WooCommerceResolvedProductWriter.
 */
class ShopifyResolvedProductWriter
{
    /**
     * Construye el payload de actualización de una variante.
     */
    public function buildVariantPayload(array $data): array
    {
        $payload = [
            'sku' => $data['sku'] ?? null,
            'price' => $data['price'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'option1' => $data['option1'] ?? null,
            'option2' => $data['option2'] ?? null,
            'option3' => $data['option3'] ?? null,
        ];

        return array_filter($payload, function ($v) {
            return $v !== null;
        });
    }

    /**
     * Actualiza una variante en Shopify a partir de una resolución por SKU
     * y vincula el producto local con los IDs remotos correspondientes.
     *
     * @param array $resolution { product_id, variant_id, inventory_item_id }
     */
    public function applyResolution(
        ShopifyApiClient $client,
        Producto $producto,
        array $variantPayload,
        array $resolution
    ): void {
        $variantId = (int) ($resolution['variant_id'] ?? 0);
        if ($variantId <= 0) {
            throw new \InvalidArgumentException('Resolución Shopify inválida: falta variant_id');
        }

        $client->put("variants/{$variantId}.json", [
            'variant' => $variantPayload,
        ]);

        $producto->shopify_product_id = $resolution['product_id'] ?? $producto->shopify_product_id;
        $producto->shopify_variant_id = $variantId;
        $producto->shopify_inventory_item_id = $resolution['inventory_item_id'] ?? $producto->shopify_inventory_item_id;
        $producto->saveQuietly();

        Log::info('ShopifyResolvedProductWriter: variante actualizada por SKU', [
            'producto_id' => $producto->id,
            'variant_id' => $variantId,
        ]);
    }
}
