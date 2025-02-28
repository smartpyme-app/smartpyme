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
            Log::info("Producto deshabilitado, no se sincronizará", [
                'producto_id' => $producto->id
            ]);
            return;
        }
        $inventarios = Inventario::where('id_producto', $producto->id)->get();

        if ($inventarios->isEmpty()) {
            Log::info("No se encontraron inventarios para este producto", [
                'producto_id' => $producto->id
            ]);
            return;
        }

        $bodegas = Bodega::whereIn('id', $inventarios->pluck('id_bodega')->toArray())->get();

        // Buscar usuarios con WooCommerce configurado que estén relacionados con la sucursal
        $usuarios = User::whereIn('id_sucursal', $bodegas->pluck('id_sucursal')->toArray())
            ->whereNotNull('woocommerce_api_key')
            ->whereNotNull('woocommerce_store_url')
            ->whereNotNull('woocommerce_consumer_key')
            ->whereNotNull('woocommerce_consumer_secret')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::info("No se encontraron usuarios con integración WooCommerce para este producto", [
                'producto_id' => $producto->id,
                'sucursal_id' => $producto->id_sucursal
            ]);
            return;
        }

        // Sincronizar con WooCommerce para cada usuario
        foreach ($usuarios as $usuario) {
            try {
                // Si existe este servicio, si no debes crearlo
                $this->stockService->actualizarStockEnWooCommerce(
                    $producto->id,
                    $usuario->id
                );
            } catch (\Exception $e) {
                Log::error("Error al sincronizar producto para usuario: " . $e->getMessage(), [
                    'usuario_id' => $usuario->id,
                    'producto_id' => $producto->id
                ]);
            }
        }
    }



    public function created(Producto $producto)
    {
        $empresa = Empresa::where('id', $producto->id_empresa)->first();

        if (!$empresa) {
            return;
        }
        $usuarios = User::where('id_empresa', $empresa->id)
            ->whereNotNull('woocommerce_api_key')
            ->whereNotNull('woocommerce_store_url')
            ->whereNotNull('woocommerce_consumer_key')
            ->whereNotNull('woocommerce_consumer_secret')
            ->get();

        if ($usuarios->isEmpty()) {
            Log::info("No se encontraron usuarios con integración WooCommerce para este producto", [
                'producto_id' => $producto->id,
                'sucursal_id' => $producto->id_sucursal
            ]);
            return;
        }


        foreach ($usuarios as $usuario) {
            try {
                // Si existe este servicio, si no debes crearlo
                $this->stockService->actualizarStockEnWooCommerce(
                    $producto->id,
                    $usuario->id
                );
            } catch (\Exception $e) {
                Log::error("Error al sincronizar producto para usuario: " . $e->getMessage(), [
                    'usuario_id' => $usuario->id,
                    'producto_id' => $producto->id
                ]);
            }
        }


        Log::info("Usuarios encontrados", [
            'usuarios' => $usuarios
        ]);

        return;
    }
}
