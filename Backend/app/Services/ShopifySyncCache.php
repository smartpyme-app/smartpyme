<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopifySyncCache
{
    /**
     * Guardar snapshot del producto en cache
     */
    public function saveProductSnapshot($producto)
    {
        $key = "shopify_product_{$producto->id}";
        
        $snapshot = [
            'precio' => $producto->precio,
            'costo' => $producto->costo,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'id_categoria' => $producto->id_categoria,
        ];
        
        Cache::forever($key, $snapshot);
        
        Log::info("Snapshot guardado", [
            'producto_id' => $producto->id,
            'snapshot' => $snapshot
        ]);
    }

    /**
     * Guardar snapshot del inventario en cache
     */
    public function saveInventorySnapshot($inventario, $productoId)
    {
        $key = "shopify_inventory_{$productoId}";
        
        $snapshot = [
            'stock' => $inventario->stock,
        ];
        
        Cache::forever($key, $snapshot);
        
        Log::info("Snapshot inventario guardado", [
            'producto_id' => $productoId,
            'stock' => $inventario->stock
        ]);
    }

    /**
     * Verificar si el producto cambió comparando con cache
     */
    public function hasProductChanged($producto)
    {
        $key = "shopify_product_{$producto->id}";
        $cached = Cache::get($key);
        
        if (!$cached) {
            // No hay cache, significa que es la primera vez
            return true;
        }
        
        $current = [
            'precio' => $producto->precio,
            'costo' => $producto->costo,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'id_categoria' => $producto->id_categoria,
        ];
        
        $changed = $cached !== $current;
        
        Log::info("Verificación cambio producto", [
            'producto_id' => $producto->id,
            'cached' => $cached,
            'current' => $current,
            'changed' => $changed
        ]);
        
        return $changed;
    }

    /**
     * Verificar si el inventario cambió
     */
    public function hasInventoryChanged($inventario, $productoId)
    {
        $key = "shopify_inventory_{$productoId}";
        $cached = Cache::get($key);
        
        if (!$cached) {
            return true;
        }
        
        $current = ['stock' => $inventario->stock];
        $changed = $cached !== $current;
        
        Log::info("Verificación cambio inventario", [
            'producto_id' => $productoId,
            'cached_stock' => $cached['stock'] ?? null,
            'current_stock' => $inventario->stock,
            'changed' => $changed
        ]);
        
        return $changed;
    }

    /**
     * Verificar si data de Shopify es diferente a la local
     */
    public function isShopifyDataDifferent($localProduct, $shopifyData)
    {
        $localData = [
            'precio' => $localProduct->precio,
            'costo' => $localProduct->costo,
            'codigo' => $localProduct->codigo,
            'nombre' => $localProduct->nombre,
            'descripcion' => $localProduct->descripcion,
            'id_categoria' => $localProduct->id_categoria,
        ];
        
        $shopifyFormatted = [
            'precio' => $shopifyData['precio'] ?? $localProduct->precio,
            'costo' => $shopifyData['costo'] ?? $localProduct->costo,
            'codigo' => $shopifyData['codigo'] ?? $localProduct->codigo,
            'nombre' => $shopifyData['nombre'] ?? $localProduct->nombre,
            'descripcion' => $shopifyData['descripcion'] ?? $localProduct->descripcion,
            'id_categoria' => $shopifyData['id_categoria'] ?? $localProduct->id_categoria,
        ];
        
        $different = $localData !== $shopifyFormatted;
        
        Log::info("Comparación Shopify vs Local", [
            'producto_id' => $localProduct->id,
            'local' => $localData,
            'shopify' => $shopifyFormatted,
            'different' => $different
        ]);
        
        return $different;
    }

    /**
     * Lock temporal para evitar loops de sincronización
     */
    public function lockSync($productoId)
    {
        $key = "sync_lock_{$productoId}";
        Cache::put($key, true, 120); // 2 minutos
    }

    public function isLocked($productoId)
    {
        $key = "sync_lock_{$productoId}";
        return Cache::has($key);
    }
}