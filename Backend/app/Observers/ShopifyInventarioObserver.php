<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\User;
use App\Services\ShopifyStockService;
use Illuminate\Support\Facades\Log;

class ShopifyInventarioObserver
{
    protected $stockService;

    public function __construct(ShopifyStockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function updated(Inventario $inventario)
    {
        if ($inventario->isDirty('stock')) {
            Log::info("Cambio de stock detectado para Shopify", [
                'producto_id' => $inventario->id_producto,
                'bodega_id' => $inventario->id_bodega,
                'stock_anterior' => $inventario->getOriginal('stock'),
                'stock_nuevo' => $inventario->stock
            ]);

            $bodega = Bodega::where('id', $inventario->id_bodega)->first();
            $empresa = Empresa::where('id', $bodega->id_empresa)
                ->whereNotNull('shopify_store_url')
                ->whereNotNull('shopify_consumer_secret')
                ->where('shopify_status', 'connected')
                ->first();

            if (!$empresa) {
                Log::info("No se encontró empresa con integración Shopify para esta bodega", [
                    'bodega_id' => $inventario->id_bodega
                ]);
                return;
            }

            $usuario = User::where('id_empresa', $empresa->id)
                ->where('shopify_status', 'connected')
                ->first();

            if (!$usuario) {
                Log::info("No se encontró usuario con integración Shopify para esta empresa", [
                    'empresa_id' => $empresa->id
                ]);
                return;
            }

            if ($inventario->id_bodega != $usuario->id_bodega) {
                return;
            }

            try {
                $this->stockService->actualizarStockEnShopify(
                    $inventario->id_producto,
                    $usuario->id
                );
            } catch (\Exception $e) {
                Log::error("Error al sincronizar stock Shopify para usuario: " . $e->getMessage(), [
                    'usuario_id' => $usuario->id,
                    'producto_id' => $inventario->id_producto
                ]);
            }
        }
    }
}