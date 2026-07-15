<?php

namespace App\Observers;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\WooCommerceProductService;
use App\Services\WooCommerceStockService;
use Illuminate\Support\Facades\Log;
use App\Models\Inventario\Bodega;

class ProductoObserver
{
    protected $stockService;

    /**
     * Constructor del observer
     */
    public function __construct(WooCommerceStockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Maneja el evento "updated" del modelo Producto
     */
    public function updated(Producto $producto)
    {
        // No sincronizar si el producto está deshabilitado
        if (!$producto->enable) {
            return;
        }

        if (!$producto->wasChanged(['precio', 'precio_sin_iva', 'precio_con_iva', 'costo', 'costo_promedio', 'nombre', 'descripcion', 'codigo'])) {
            return;
        }

        $empresaBase = Empresa::where('id', $producto->id_empresa)->first();

        if (!$empresaBase) {
            return;
        }

        if (empty($empresaBase->woocommerce_status) ||
            $empresaBase->woocommerce_status === 'disconnected' ||
            $empresaBase->woocommerce_status === 'disabled') {
            return;
        }

        try {
            $empresa = Empresa::where('id', $producto->id_empresa)
                ->whereNotNull('woocommerce_api_key')
                ->whereNotNull('woocommerce_store_url')
                ->whereNotNull('woocommerce_consumer_key')
                ->whereNotNull('woocommerce_consumer_secret')
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$empresa) {
                return;
            }

            $usuario = User::where('id_empresa', $empresa->id)
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$usuario) {
                return;
            }

            $this->stockService->actualizarStockEnWooCommerce(
                $producto->id,
                $usuario->id,
                $this->collectChangedSyncFields($producto)
            );
        } catch (\Throwable $e) {
            Log::error("Error al sincronizar producto con WooCommerce: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'empresa_id' => $producto->id_empresa,
            ]);
        }
    }



    public function created(Producto $producto)
    {
        $empresaBase = Empresa::where('id', $producto->id_empresa)->first();

        if (!$empresaBase) {
            return;
        }

        if (empty($empresaBase->woocommerce_status) ||
            $empresaBase->woocommerce_status === 'disconnected' ||
            $empresaBase->woocommerce_status === 'disabled') {
            return;
        }

        try {
            $empresa = Empresa::where('id', $producto->id_empresa)
                ->whereNotNull('woocommerce_api_key')
                ->whereNotNull('woocommerce_store_url')
                ->whereNotNull('woocommerce_consumer_key')
                ->whereNotNull('woocommerce_consumer_secret')
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$empresa) {
                return;
            }

            $usuario = User::where('id_empresa', $empresa->id)
                ->where('woocommerce_status', 'connected')
                ->first();

            if (!$usuario) {
                return;
            }

            $this->stockService->actualizarStockEnWooCommerce(
                $producto->id,
                $usuario->id
            );
        } catch (\Throwable $e) {
            Log::error("Error al sincronizar producto nuevo con WooCommerce: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'empresa_id' => $producto->id_empresa,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function collectChangedSyncFields(Producto $producto): array
    {
        $fields = [];

        foreach (['precio', 'precio_sin_iva', 'precio_con_iva', 'costo', 'costo_promedio', 'nombre', 'descripcion', 'codigo'] as $field) {
            if ($producto->wasChanged($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}