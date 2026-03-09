<?php

namespace App\Observers;

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
        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            return;
        }

        $empresa = Empresa::find($bodega->id_empresa);
        if (!$empresa) {
            return;
        }

        // Si la empresa tiene Shopify conectado, sincronizar SmartPyme -> Shopify (ajustes, compras, etc.)
        if ($empresa->shopify_status === 'connected'
            && !empty($empresa->shopify_store_url)
            && !empty($empresa->shopify_consumer_secret)) {
            $this->syncBidirectional($inventario);
        }

        // Lógica anterior: solo sincronizaba si shopify_sync_bidirectional estaba activo (comentado)
        // if ($empresa->shopify_sync_bidirectional) {
        //     Log::info("Sincronización inversa habilitada para actualizaciones de inventario - SmartPyme -> Shopify ", [
        //         'inventario_id' => $inventario->id,
        //         'producto_id' => $inventario->id_producto,
        //         'stock' => $inventario->stock,
        //         'motivo' => 'Sincronización unidireccional configurada'
        //     ]);
        //     $this->syncBidirectional($inventario);
        // }
        // Si no es bidireccional, no hacer nada
        // Log::info("Sincronización inversa deshabilitada para actualizaciones de inventario - solo Shopify -> SmartPyme", [...]);
    }

    // Para actualizacion de stock doble direccional (SmartPyme -> Shopify)
    public function syncBidirectional(Inventario $inventario)
    {
        // wasChanged: en evento "updated" el modelo ya fue guardado; isDirty sería false
        if (!$inventario->wasChanged('stock')) {
            return;
        }

        // IMPORTANTE: Verificar si el producto está siendo sincronizado desde Shopify
        $producto = $inventario->producto;
        if ($producto && $producto->syncing_from_shopify) {
            Log::info("Producto siendo sincronizado desde Shopify, omitiendo sincronización de inventario", [
                'inventario_id' => $inventario->id,
                'producto_id' => $inventario->id_producto,
                'syncing_from_shopify' => $producto->syncing_from_shopify
            ]);
            return;
        }

        Log::info("Cambio de stock detectado para Shopify", [
            'inventario_id' => $inventario->id,
            'producto_id' => $inventario->id_producto,
            'bodega_id' => $inventario->id_bodega,
            'stock_anterior' => $inventario->getOriginal('stock'),
            'stock_nuevo' => $inventario->stock
        ]);

        if ($this->cache->isLocked($inventario->id_producto)) {
            Log::debug("Sync Shopify omitido: producto bloqueado", ['producto_id' => $inventario->id_producto]);
            return;
        }

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            Log::debug("Sync Shopify omitido: bodega no encontrada", ['id_bodega' => $inventario->id_bodega]);
            return;
        }

        // Verificar si la empresa tiene Shopify habilitado antes de intentar sincronizar
        $empresaBase = Empresa::find($bodega->id_empresa);
        if (!$empresaBase) return;

        // VALIDACIÓN PREVIA: Solo continuar si la empresa tiene intención de usar Shopify
        if (empty($empresaBase->shopify_status) || 
            $empresaBase->shopify_status === 'disconnected' || 
            $empresaBase->shopify_status === 'disabled') {
            
            Log::debug("Empresa sin integración Shopify habilitada - omitiendo sincronización", [
                'bodega_id' => $inventario->id_bodega,
                'empresa_id' => $empresaBase->id,
                'empresa_nombre' => $empresaBase->nombre,
                'shopify_status' => $empresaBase->shopify_status ?? 'null'
            ]);
            return;
        }

        $empresa = Empresa::where('id', $bodega->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) {
            if ($empresaBase->shopify_status === 'connecting') {
                Log::info("Empresa en proceso de configuración Shopify - sincronización pendiente", [
                    'bodega_id' => $inventario->id_bodega,
                    'empresa_id' => $empresaBase->id,
                    'current_status' => $empresaBase->shopify_status
                ]);
            }
            return;
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('id_bodega', $inventario->id_bodega)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            Log::warning("Sync Shopify omitido: no hay usuario con Shopify conectado en esta bodega", [
                'producto_id' => $inventario->id_producto,
                'id_bodega' => $inventario->id_bodega,
                'id_empresa' => $empresa->id,
            ]);
            return;
        }

        Log::info("Iniciando sincronización con Shopify", [
            'inventario_id' => $inventario->id,
            'stock' => $inventario->stock,
            'producto_id' => $inventario->id_producto,
            'bodega_id' => $inventario->id_bodega,
            'usuario_id' => $usuario->id
        ]);

        if (!$this->cache->hasInventoryChanged($inventario, $inventario->id_producto)) {
            Log::debug("Sync Shopify omitido: cache indica que el inventario no cambió", [
                'producto_id' => $inventario->id_producto,
                'stock' => $inventario->stock,
            ]);
            return;
        }
        $success = $this->stockService->actualizarSoloStockEnShopify(
            $inventario->id_producto,
            $usuario->id
        );

        if ($success) {
            $this->cache->saveInventorySnapshot($inventario, $inventario->id_producto);
        }
    }
    
}
