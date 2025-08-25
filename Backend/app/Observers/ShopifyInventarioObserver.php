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
        Log::info("Inventario actualizado", [
            'inventario_id' => $inventario->id,
        ]);

        if ($this->cache->isLocked($inventario->id_producto)) {
            return;
        }

        $bodega = Bodega::find($inventario->id_bodega);
        if (!$bodega) return;

        $empresa = Empresa::where('id', $bodega->id_empresa)
            ->whereNotNull('shopify_store_url')
            ->whereNotNull('shopify_consumer_secret')
            ->where('shopify_status', 'connected')
            ->first();
            Log::info("Empresa encontrada", [
                'empresa_id' => $empresa->id,
                'bodega_id' => $bodega->id
            ]);

        if (!$empresa) return;

        $usuario = User::where('id_empresa', $empresa->id)
            ->where('shopify_status', 'connected')
            ->first();
            Log::info("Usuario encontrado", [
                'usuario_id' => $usuario->id,
                'bodega_id' => $bodega->id,
                'bodega_id_usuario' => $usuario->id_bodega
            ]);

        if (!$usuario || $inventario->id_bodega != $usuario->id_bodega) {
            return;
        }
        Log::info("Inventario actualizado", [
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
