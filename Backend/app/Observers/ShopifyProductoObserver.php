<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyStockService;
use App\Services\ShopifySyncCache;

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

        // Evitar loops de sincronización
        if ($this->cache->isLocked($producto->id)) {
            return;
        }

        // Solo verificar campos relevantes
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

        // COMPARAR con cache - solo sincronizar si cambió
        if (!$this->cache->hasProductChanged($producto)) {
            return; // No cambió, no hacer nada
        }

        // Sincronizar a Shopify
        $success = $this->stockService->actualizarProductoCompletoEnShopify(
            $producto->id,
            $usuario->id,
            false
        );

        // Guardar nuevo snapshot si fue exitoso
        if ($success) {
            $this->cache->saveProductSnapshot($producto);
        }
    }

    public function created(Producto $producto)
    {
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

            // Guardar snapshot inicial
            if ($success) {
                $this->cache->saveProductSnapshot($producto);
            }
        }
    }
}