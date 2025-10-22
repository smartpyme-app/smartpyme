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

    public function updated(Inventario $inventario)
    {
        if (!$inventario->isDirty('stock')) {
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
            return;
        }

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) return;

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
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario || $inventario->id_bodega != $usuario->id_bodega) {
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
