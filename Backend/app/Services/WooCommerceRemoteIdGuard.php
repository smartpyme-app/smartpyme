<?php

namespace App\Services;

use App\Models\Inventario\Producto;

/**
 * IDs guardados en SP que ya no existen en la tienda Woo actual (tienda nueva, producto borrado).
 */
final class WooCommerceRemoteIdGuard
{
    public static function isMissingRemoteProduct(\Exception $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'woocommerce_rest_product_invalid_id')
            || str_contains($msg, 'woocommerce_rest_product_variation_invalid_id')
            || (str_contains($msg, '"status":404') && str_contains($msg, 'woocommerce'));
    }

    public static function clearLocalIds(Producto $producto): void
    {
        $producto->woocommerce_id = null;
        $producto->woocommerce_parent_id = null;
        $producto->saveQuietly();
    }
}
