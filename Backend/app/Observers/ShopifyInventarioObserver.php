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
        // ShopifyHelper::log("ShopifyInventarioObserver::updated disparado", [
        //     'inventario_id' => $inventario->id,
        //     'producto_id' => $inventario->id_producto,
        //     'bodega_id' => $inventario->id_bodega,
        //     'was_changed_stock' => $inventario->wasChanged('stock'),
        //     'stock_anterior' => $inventario->getOriginal('stock'),
        //     'stock_nuevo' => $inventario->stock,
        // ]);

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            // ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: bodega no encontrada", [
            //     'inventario_id' => $inventario->id,
            //     'id_bodega' => $inventario->id_bodega
            // ]);
            return;
        }

        $empresa = Empresa::find($bodega->id_empresa);
        if (!$empresa) {
            // ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: empresa no encontrada", [
            //     'inventario_id' => $inventario->id,
            //     'id_empresa' => $bodega->id_empresa
            // ]);
            return;
        }

        // Si la empresa tiene Shopify conectado, sincronizar SmartPyme -> Shopify (ajustes, compras, etc.)
        if ($empresa->shopify_status === 'connected'
            && $empresa->tieneCredencialesShopify()) {
            // ShopifyHelper::log("ShopifyInventarioObserver::updated: Empresa conectada con credenciales, llamando syncBidirectional", [
            //     'inventario_id' => $inventario->id,
            //     'empresa_id' => $empresa->id
            // ]);
            $this->syncBidirectional($inventario);
        } else {
            // ShopifyHelper::log("ShopifyInventarioObserver::updated omitido: empresa no conectada o sin credenciales", [
            //     'inventario_id' => $inventario->id,
            //     'empresa_id' => $empresa->id,
            //     'shopify_status' => $empresa->shopify_status ?? 'null',
            // ]);
        }
    }

    // Para actualizacion de stock doble direccional (SmartPyme -> Shopify)
    public function syncBidirectional(Inventario $inventario)
    {
        // wasChanged: en evento "updated" el modelo ya fue guardado; isDirty sería false
        if (!$inventario->wasChanged('stock')) {
            // ShopifyHelper::log("syncBidirectional omitido: wasChanged('stock') es false", [
            //     'inventario_id' => $inventario->id,
            //     'producto_id' => $inventario->id_producto,
            //     'bodega_id' => $inventario->id_bodega,
            //     'stock' => $inventario->stock,
            // ]);
            return;
        }

        // IMPORTANTE: Verificar si el producto está siendo sincronizado desde Shopify
        $producto = $inventario->producto;
        // ShopifyHelper::log("syncBidirectional: evaluando flag syncing_from_shopify", [
        //     'inventario_id' => $inventario->id,
        //     'producto_id' => $inventario->id_producto,
        //     'producto_encontrado' => (bool)$producto,
        //     'syncing_from_shopify' => $producto ? (bool)$producto->syncing_from_shopify : false,
        // ]);

        if ($producto && $producto->syncing_from_shopify) {
            // ShopifyHelper::log("Producto siendo sincronizado desde Shopify, omitiendo sincronización de inventario para evitar ciclo", [
            //     'inventario_id' => $inventario->id,
            //     'producto_id' => $inventario->id_producto,
            //     'syncing_from_shopify' => $producto->syncing_from_shopify
            // ]);
            return;
        }

        // No usar isLocked aquí: el lock se pone al procesar products/update desde Shopify
        // y bloquearía enviar ajustes desde SmartPyme a Shopify durante 2 min.

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) {
            // ShopifyHelper::log("Sync Shopify omitido: bodega no encontrada", ['id_bodega' => $inventario->id_bodega], 'warning');
            return;
        }

        // Verificar si la empresa tiene Shopify habilitado antes de intentar sincronizar
        $empresaBase = Empresa::find($bodega->id_empresa);
        if (!$empresaBase) return;

        // VALIDACIÓN PREVIA: Solo continuar si la empresa tiene intención de usar Shopify
        if (empty($empresaBase->shopify_status) || 
            $empresaBase->shopify_status === 'disconnected' || 
            $empresaBase->shopify_status === 'disabled') {
            
            // ShopifyHelper::log("Empresa sin integración Shopify habilitada - omitiendo sincronización", [
            //     'bodega_id' => $inventario->id_bodega,
            //     'empresa_id' => $empresaBase->id,
            //     'empresa_nombre' => $empresaBase->nombre,
            //     'shopify_status' => $empresaBase->shopify_status ?? 'null'
            // ]);
            return;
        }

        $empresa = Empresa::where('id', $bodega->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->where('shopify_status', 'connected')
            ->get()
            ->first(fn ($e) => $e->tieneCredencialesShopify());

        if (!$empresa) {
            if ($empresaBase->shopify_status === 'connecting') {
                // ShopifyHelper::log("Empresa en proceso de configuración Shopify - sincronización pendiente", [
                //     'bodega_id' => $inventario->id_bodega,
                //     'empresa_id' => $empresaBase->id,
                //     'current_status' => $empresaBase->shopify_status
                // ]);
            }
            return;
        }

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('id_bodega', $inventario->id_bodega)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) {
            // ShopifyHelper::log("Sync Shopify omitido: no hay usuario con Shopify conectado en esta bodega", [
            //     'producto_id' => $inventario->id_producto,
            //     'id_bodega' => $inventario->id_bodega,
            //     'id_empresa' => $empresa->id,
            // ], 'warning');
            return;
        }

        $hasChanged = $this->cache->hasInventoryChanged($inventario, $inventario->id_producto);
        // ShopifyHelper::log("syncBidirectional: resultado de hasInventoryChanged", [
        //     'producto_id' => $inventario->id_producto,
        //     'stock_actual' => $inventario->stock,
        //     'has_changed' => $hasChanged,
        // ]);

        if (!$hasChanged) {
            // ShopifyHelper::log("Sync Shopify omitido: cache indica que el inventario no cambió", [
            //     'producto_id' => $inventario->id_producto,
            //     'stock' => $inventario->stock,
            // ]);
            return;
        }

        // ShopifyHelper::log("Iniciando sincronización con Shopify", [
        //     'inventario_id' => $inventario->id,
        //     'stock' => $inventario->stock,
        //     'producto_id' => $inventario->id_producto,
        //     'bodega_id' => $inventario->id_bodega,
        //     'usuario_id' => $usuario->id
        // ]);

        $success = $this->stockService->actualizarSoloStockEnShopify(
            $inventario->id_producto,
            $usuario->id
        );

        // ShopifyHelper::log("Resultado de sincronización con Shopify", [
        //     'producto_id' => $inventario->id_producto,
        //     'stock' => $inventario->stock,
        //     'success' => $success,
        // ]);

        if ($success) {
            $this->cache->saveInventorySnapshot($inventario, $inventario->id_producto);
        }
    }
    
}
