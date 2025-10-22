<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyStockService;
use App\Services\ShopifySyncCache;
use Illuminate\Support\Facades\Log;

class ShopifyProductoObserver
{
    protected $stockService;
    protected $cache;

    public function __construct(ShopifyStockService $stockService, ShopifySyncCache $cache)
    {
        $this->stockService = $stockService;
        $this->cache = $cache;
    }

    public function updated(Producto $producto)
    {
        if (!$producto->enable) {
            return;
        }

        // PREVENIR CICLO: No sincronizar productos que están siendo actualizados desde Shopify
        if ($producto->syncing_from_shopify) {
            Log::info("Producto siendo sincronizado desde Shopify, omitiendo sincronización inversa", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'syncing_from_shopify' => $producto->syncing_from_shopify
            ]);
            return;
        }

        if ($this->cache->isLocked($producto->id)) {
            return;
        }
        
        $camposRelevantes = ['precio', 'costo', 'codigo', 'nombre', 'descripcion', 'id_categoria'];
        $hayCambios = false;

        foreach ($camposRelevantes as $campo) {
            if ($producto->isDirty($campo)) {
                $hayCambios = true;
                break;
            }
        }

        if (!$hayCambios) {
            return;
        }

        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) return;

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();

        if (!$usuario) return;

        if (!$this->cache->hasProductChanged($producto)) {
            return;
        }
        
        // SINCRONIZAR A SHOPIFY: Tanto productos locales como productos que vinieron de Shopify
        // pero que ahora se están editando desde el sistema local
        $success = $this->stockService->actualizarProductoCompletoEnShopify(
            $producto->id,
            $usuario->id,
            false
        );

        if ($success) {
            $this->cache->saveProductSnapshot($producto);
        }
    }

    public function created(Producto $producto)
    {
        // PREVENIR CICLO: No sincronizar productos que vienen de Shopify
        if ($producto->shopify_product_id || $producto->syncing_from_shopify) {
            return;
        }

        // Verificar si está en proceso de sincronización desde webhook
        if ($this->cache->isLocked($producto->id)) {
            return;
        }

        $empresa = Empresa::where('id', $producto->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();

        if (!$empresa) return;

        $usuarios = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->get();

        foreach ($usuarios as $usuario) {
            $success = $this->stockService->createdProductoCompletoEnShopify(
                $producto->id,
                $usuario->id,
                true
            );

            if ($success) {
                $this->cache->saveProductSnapshot($producto);
            }
        }
    }
}