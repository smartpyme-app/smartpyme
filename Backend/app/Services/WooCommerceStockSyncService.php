<?php

namespace App\Services;

use Automattic\WooCommerce\Client;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WooCommerceStockSyncService
{
    /**
     * Actualiza el stock de un producto en WooCommerce
     * 
     * @param int $productoId ID del producto en Smartpyme
     * @param int $userId ID del usuario con la integración WooCommerce
     * @return bool
     */
    public function actualizarStockEnWooCommerce($productoId, $userId)
    {
        try {
            // Obtener el usuario y verificar que tenga configuración de WooCommerce
            $usuario = User::find($userId);
            
            if (!$usuario || empty($usuario->woocommerce_api_key)) {
                Log::error("Usuario no encontrado o sin API key de WooCommerce", ['user_id' => $userId]);
                return false;
            }
            
            // Obtener el producto y su stock actual
            $producto = Producto::find($productoId);
            
            if (!$producto) {
                Log::error("Producto no encontrado", ['producto_id' => $productoId]);
                return false;
            }
            
            // Si el producto no tiene un ID de WooCommerce, no podemos actualizarlo
            if (empty($producto->woocommerce_id)) {
                Log::warning("Producto sin ID de WooCommerce", ['producto_id' => $productoId, 'nombre' => $producto->nombre]);
                return false;
            }
            
            // Calcular el stock total del producto en todas las bodegas o en la bodega principal
            $stock = Inventario::where('id_producto', $productoId)
                               ->where('id_bodega', $usuario->id_bodega) // O usar sum() para todas las bodegas
                               ->value('stock');
            
            if ($stock === null) {
                Log::warning("No se encontró inventario para el producto", ['producto_id' => $productoId]);
                $stock = 0;
            }
            
            // Obtener credenciales de WooCommerce (podrían estar en config o en la BD)
            $storeUrl = config('woocommerce.store_url');
            $consumerKey = config('woocommerce.consumer_key');
            $consumerSecret = config('woocommerce.consumer_secret');
            
            if (empty($storeUrl) || empty($consumerKey) || empty($consumerSecret)) {
                Log::error("Faltan credenciales de WooCommerce");
                return false;
            }
            
            // Inicializar cliente de WooCommerce
            $woocommerce = new Client(
                $storeUrl,
                $consumerKey,
                $consumerSecret,
                [
                    'version' => 'wc/v3',
                    'timeout' => 30
                ]
            );
            
            // Actualizar el stock en WooCommerce
            $response = $woocommerce->put('products/' . $producto->woocommerce_id, [
                'stock_quantity' => $stock,
                'manage_stock' => true
            ]);
            
            Log::info("Stock actualizado en WooCommerce", [
                'producto_id' => $productoId,
                'woocommerce_id' => $producto->woocommerce_id,
                'stock' => $stock
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error al actualizar stock en WooCommerce: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Sincroniza el stock de múltiples productos en WooCommerce
     * 
     * @param array $productoIds Array de IDs de productos
     * @param int $userId ID del usuario
     * @return array Resultados de la sincronización
     */
    public function sincronizarMultiplesProductos(array $productoIds, $userId)
    {
        $resultados = [
            'total' => count($productoIds),
            'exitosos' => 0,
            'fallidos' => 0,
            'detalles' => []
        ];
        
        foreach ($productoIds as $productoId) {
            $exito = $this->actualizarStockEnWooCommerce($productoId, $userId);
            
            if ($exito) {
                $resultados['exitosos']++;
            } else {
                $resultados['fallidos']++;
            }
            
            $resultados['detalles'][] = [
                'producto_id' => $productoId,
                'resultado' => $exito ? 'exitoso' : 'fallido'
            ];
        }
        
        return $resultados;
    }
}