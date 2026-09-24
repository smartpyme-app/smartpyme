<?php

namespace App\Observers;

use App\Helpers\ShopifyHelper;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\User;
use App\Services\ShopifyStockService;
use App\Services\ShopifySyncCache;
use Illuminate\Support\Facades\Log;

class ShopifyInventarioObserver
{
    protected $stockService;
    protected $cache;

    public function __construct(ShopifyStockService $stockService, ShopifySyncCache $cache)
    {
        $this->stockService = $stockService;
        $this->cache = $cache;
    }


    /**
     * Cuando el inventario cambia en SmartPyme (ajuste, compra, etc.), envía el nuevo stock a Shopify
     * si la empresa tiene Shopify conectado.
     */
    public function updated(Inventario $inventario)
    {
        ShopifyHelper::log("ShopifyInventarioObserver::updated disparado", [
            'inventario_id' => $inventario->id,
            'producto_id' => $inventario->id_producto,
            'bodega_id' => $inventario->id_bodega,
            'was_changed_stock' => $inventario->wasChanged('stock'),
            'stock_anterior' => $inventario->getOriginal('stock'),
            'stock_nuevo' => $inventario->stock,
        ]);

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: bodega no encontrada", [
                'inventario_id' => $inventario->id,
                'id_bodega' => $inventario->id_bodega
            ]);
            return;
        }

        $empresa = Empresa::find($bodega->id_empresa);
        if (!$empresa) {
            ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: empresa no encontrada", [
                'inventario_id' => $inventario->id,
                'id_empresa' => $bodega->id_empresa
            ]);
            return;
        }

        // Si la empresa tiene Shopify conectado, sincronizar SmartPyme -> Shopify (ajustes, compras, etc.)
        if ($empresa->shopify_status === 'connected'
            && $empresa->tieneCredencialesShopify()) {
            ShopifyHelper::log("ShopifyInventarioObserver::updated: Empresa conectada con credenciales, llamando syncBidirectional", [
                'inventario_id' => $inventario->id,
                'empresa_id' => $empresa->id
            ]);
            $this->syncBidirectional($inventario);
        } else {
            ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: empresa no conectada o sin credenciales", [
                'inventario_id' => $inventario->id,
                'empresa_id' => $empresa->id,
                'shopify_status' => $empresa->shopify_status ?? 'null',
            ]);
        }
    }

    // Para actualizacion de stock doble direccional (SmartPyme -> Shopify)
    public function syncBidirectional(Inventario $inventario)
    {
        // wasChanged: en evento "updated" el modelo ya fue guardado; isDirty sería false
        if (!$inventario->wasChanged('stock')) {
            ShopifyHelper::log("syncBidirectional omitido: wasChanged('stock') es false", [
                'inventario_id' => $inventario->id,
                'producto_id' => $inventario->id_producto,
                'bodega_id' => $inventario->id_bodega,
                'stock' => $inventario->stock,
            ]);
            return;
        }

        // BUG-1 fix: verificar clave de cache en lugar de leer syncing_from_shopify de BD.
        // La clave la pone actualizarInventario() con TTL 60s; expira automáticamente
        // si el proceso muere, evitando bloqueos permanentes.
        if (\Illuminate\Support\Facades\Cache::has("shopify_syncing_inv_{$inventario->id_producto}")) {
            ShopifyHelper::log("Producto siendo sincronizado desde Shopify, omitiendo sincronización hacia Shopify para evitar ciclo", [
                'inventario_id' => $inventario->id,
                'producto_id'   => $inventario->id_producto,
            ]);
            return;
        }

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            ShopifyHelper::log("Sync Shopify omitido: bodega no encontrada", ['id_bodega' => $inventario->id_bodega], 'warning');
            return;
        }

        // Verificar si la empresa tiene Shopify habilitado antes de intentar sincronizar
        $empresaBase = Empresa::find($bodega->id_empresa);
        if (!$empresaBase) return;

        // VALIDACIÓN PREVIA: Solo continuar si la empresa tiene intención de usar Shopify
        if (empty($empresaBase->shopify_status) || 
            $empresaBase->shopify_status === 'disconnected' || 
            $empresaBase->shopify_status === 'disabled') {
            
            ShopifyHelper::log("Empresa sin integración Shopify habilitada - omitiendo sincronización", [
                'bodega_id' => $inventario->id_bodega,
                'empresa_id' => $empresaBase->id,
                'empresa_nombre' => $empresaBase->nombre,
                'shopify_status' => $empresaBase->shopify_status ?? 'null'
            ]);
            return;
        }

        $empresa = Empresa::where('id', $bodega->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->where('shopify_status', 'connected')
            ->get()
            ->first(fn ($e) => $e->tieneCredencialesShopify());

        if (!$empresa) {
            return;
        }

        // Verificar si la bodega está mapeada a una ubicación de Shopify con sincronización activa
        $mapping = \App\Models\Admin\ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->where('id_bodega', $inventario->id_bodega)
            ->where('sincronizar_stock', true)
            ->first();

        $tieneMapeos = \App\Models\Admin\ShopifyLocation::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresa->id)
            ->exists();

        // Si ya hay ubicaciones configuradas pero esta bodega no está mapeada para sincronizar, omitir
        if ($tieneMapeos && !$mapping) {
            ShopifyHelper::log("Sync hacia Shopify omitido: bodega {$inventario->id_bodega} no está configurada para sincronizar stock", [
                'inventario_id' => $inventario->id,
                'bodega_id' => $inventario->id_bodega,
            ]);
            return;
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        // Si no hay mapeos configurados aún, fallback al usuario conectado tradicional
        if (!$mapping && (!$usuario || $usuario->id_bodega != $inventario->id_bodega)) {
            return;
        }

        $hasChanged = $this->cache->hasInventoryChanged($inventario, $inventario->id_producto);

        if (!$hasChanged) {
            ShopifyHelper::log("Sync hacia Shopify omitido: stock en cache es idéntico", [
                'producto_id' => $inventario->id_producto,
                'stock' => $inventario->stock,
            ]);
            return;
        }

        ShopifyHelper::log("Sync hacia Shopify: Enviando nuevo stock", [
            'producto_id' => $inventario->id_producto,
            'bodega_id' => $inventario->id_bodega,
            'nuevo_stock' => $inventario->stock,
        ]);

        $success = $this->stockService->actualizarSoloStockEnShopify(
            $inventario->id_producto,
            $usuario ? $usuario->id : null,
            $inventario->id_bodega
        );

        if ($success) {
            $this->cache->saveInventorySnapshot($inventario, $inventario->id_producto);
            ShopifyHelper::log("Sync hacia Shopify: Stock actualizado exitosamente en Shopify", [
                'producto_id' => $inventario->id_producto,
                'bodega_id' => $inventario->id_bodega,
                'stock' => $inventario->stock,
            ]);
        } else {
            ShopifyHelper::log("Sync hacia Shopify: Falló la actualización de stock en Shopify", [
                'producto_id' => $inventario->id_producto,
                'bodega_id' => $inventario->id_bodega,
            ], 'error');
        }
    }
    
}
