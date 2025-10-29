<?php

namespace App\Services;

use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    /**
     * Tipos de envío configurados con sus precios (IVA incluido)
     */
    private const SHIPPING_TYPES = [
        'Envio SS' => [
            'price' => 3.00,
            'name' => 'Envío San Salvador',
            'description' => 'Envío dentro de San Salvador',
            'search_name' => 'Envío San Salvador' // Nombre exacto en SmartPyme
        ],
        'Envío Departamental' => [
            'price' => 4.00,
            'name' => 'Envío Departamental',
            'description' => 'Envío a otros departamentos',
            'search_name' => 'Envío Departamental' // Nombre exacto en SmartPyme
        ]
    ];

    /**
     * Procesa los tipos de envío de Shopify y los agrega como detalles de venta
     *
     * @param array $shippingLines Líneas de envío de Shopify
     * @param int $ventaId ID de la venta
     * @param int $empresaId ID de la empresa
     * @param int $usuarioId ID del usuario
     * @param int $sucursalId ID de la sucursal
     * @return array Detalles de envío creados
     */
    public function procesarTiposEnvio(array $shippingLines, int $ventaId, int $empresaId, int $usuarioId, int $sucursalId): array
    {
        $detallesEnvio = [];

        foreach ($shippingLines as $shippingLine) {
            $detalleEnvio = $this->procesarTipoEnvio($shippingLine, $ventaId, $empresaId, $usuarioId, $sucursalId);
            
            if ($detalleEnvio) {
                $detallesEnvio[] = $detalleEnvio;
            }
        }

        return $detallesEnvio;
    }

    /**
     * Procesa un tipo de envío individual
     *
     * @param array $shippingLine Línea de envío de Shopify
     * @param int $ventaId ID de la venta
     * @param int $empresaId ID de la empresa
     * @param int $usuarioId ID del usuario
     * @param int $sucursalId ID de la sucursal
     * @return Detalle|null Detalle de envío creado o null si no se procesa
     */
    private function procesarTipoEnvio(array $shippingLine, int $ventaId, int $empresaId, int $usuarioId, int $sucursalId): ?Detalle
    {
        $title = $shippingLine['title'] ?? '';
        
        // Usar el precio con descuento si está disponible, sino el precio original
        $price = floatval($shippingLine['discounted_price'] ?? $shippingLine['price'] ?? 0);
        $originalPrice = floatval($shippingLine['price'] ?? 0);
        $discount = $originalPrice - $price;

        Log::info("Procesando tipo de envío", [
            'title' => $title,
            'original_price' => $originalPrice,
            'discounted_price' => $price,
            'discount' => $discount,
            'venta_id' => $ventaId
        ]);

        // Verificar si es un tipo de envío conocido
        if (!isset(self::SHIPPING_TYPES[$title])) {
            Log::info("Tipo de envío no reconocido, saltando", [
                'title' => $title,
                'price' => $price
            ]);
            return null;
        }

        $shippingConfig = self::SHIPPING_TYPES[$title];
        
        // Verificar que el precio original coincida (con tolerancia de centavos)
        // El precio con descuento puede ser diferente, pero el original debe coincidir
        if (abs($originalPrice - $shippingConfig['price']) > 0.01) {
            Log::warning("Precio original de envío no coincide con configuración", [
                'title' => $title,
                'original_price_received' => $originalPrice,
                'discounted_price' => $price,
                'price_expected' => $shippingConfig['price']
            ]);
            return null;
        }

        // Buscar o crear el producto de envío
        $productoEnvio = $this->buscarOCrearProductoEnvio($title, $shippingConfig, $empresaId, $usuarioId, $sucursalId);

        if (!$productoEnvio) {
            Log::error("No se pudo crear/encontrar producto de envío", [
                'title' => $title
            ]);
            return null;
        }

        // Calcular precios originales y con descuento
        $precioOriginalConIVA = $originalPrice; // Precio original con IVA
        $precioOriginalSinIVA = $precioOriginalConIVA / 1.13; // Precio original sin IVA
        $ivaOriginal = $precioOriginalConIVA - $precioOriginalSinIVA;
        
        $precioConDescuentoConIVA = $price; // Precio con descuento con IVA
        $precioConDescuentoSinIVA = $precioConDescuentoConIVA / 1.13; // Precio con descuento sin IVA
        $ivaConDescuento = $precioConDescuentoConIVA - $precioConDescuentoSinIVA;
        
        // Calcular descuento sin IVA
        $descuentoSinIVA = $precioOriginalSinIVA - $precioConDescuentoSinIVA;

        // Crear el detalle de venta para el envío
        $detalleEnvio = Detalle::create([
            'id_venta' => $ventaId,
            'id_producto' => $productoEnvio->id,
            'descripcion' => $shippingConfig['name'],
            'cantidad' => 1,
            'precio' => $precioOriginalSinIVA, // Precio original sin IVA
            'costo' => 0, // Los envíos no tienen costo
            'descuento' => $descuentoSinIVA, // Descuento sin IVA
            'total' => $precioConDescuentoSinIVA, // Total con descuento sin IVA
            'no_sujeta' => 0,
            'exenta' => 0,
            'gravada' => $precioConDescuentoSinIVA, // Monto gravado con descuento sin IVA
            'iva' => $ivaConDescuento, // IVA con descuento
            'id_vendedor' => $usuarioId
        ]);

        Log::info("Detalle de envío creado", [
            'detalle_id' => $detalleEnvio->id,
            'producto_id' => $productoEnvio->id,
            'precio_original_sin_iva' => $precioOriginalSinIVA,
            'precio_original_con_iva' => $precioOriginalConIVA,
            'precio_con_descuento_sin_iva' => $precioConDescuentoSinIVA,
            'precio_con_descuento_con_iva' => $precioConDescuentoConIVA,
            'descuento_sin_iva' => $descuentoSinIVA,
            'descuento_con_iva' => $discount,
            'iva_original' => $ivaOriginal,
            'iva_con_descuento' => $ivaConDescuento
        ]);

        return $detalleEnvio;
    }

    /**
     * Busca el producto de envío existente
     *
     * @param string $title Título del envío
     * @param array $shippingConfig Configuración del envío
     * @param int $empresaId ID de la empresa
     * @param int $usuarioId ID del usuario
     * @param int $sucursalId ID de la sucursal
     * @return Producto|null Producto de envío
     */
    private function buscarOCrearProductoEnvio(string $title, array $shippingConfig, int $empresaId, int $usuarioId, int $sucursalId): ?Producto
    {
        // Buscar producto existente por nombre exacto en la categoría "envios"
        $producto = Producto::where('nombre', $shippingConfig['search_name'])
            ->where('id_empresa', $empresaId)
            ->where('tipo', 'Servicio')
            ->whereHas('categoria', function($query) {
                $query->where('nombre', 'envios');
            })
            ->first();

        if ($producto) {
            Log::info("Producto de envío encontrado", [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio_smartpyme' => $producto->precio,
                'precio_shopify' => $shippingConfig['price']
            ]);
            return $producto;
        }

        Log::warning("Producto de envío no encontrado", [
            'nombre_buscado' => $shippingConfig['search_name'],
            'empresa_id' => $empresaId,
            'categoria' => 'envios'
        ]);

        return null;
    }


    /**
     * Obtiene los tipos de envío configurados
     *
     * @return array Tipos de envío
     */
    public static function getTiposEnvio(): array
    {
        return self::SHIPPING_TYPES;
    }
}
