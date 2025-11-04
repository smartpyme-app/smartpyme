<?php

namespace App\Services;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Ventas\Detalle;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    protected $impuestosService;

    public function __construct(ImpuestosService $impuestosService)
    {
        $this->impuestosService = $impuestosService;
    }

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
     * Procesa un tipo de envío individual de forma dinámica
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

        // Validar que tenga un título válido
        if (empty($title)) {
            Log::warning("Shipping line sin título, saltando", [
                'shipping_line' => $shippingLine,
                'venta_id' => $ventaId
            ]);
            return null;
        }

        // Usar el precio con descuento si está disponible, sino el precio original
        $price = floatval($shippingLine['discounted_price'] ?? $shippingLine['price'] ?? 0);
        $originalPrice = floatval($shippingLine['price'] ?? 0);
        $discount = $originalPrice - $price;

        // Si el precio final es 0, no procesar (envío gratis)
        if ($price <= 0) {
            Log::info("Envío gratis detectado, no se crea detalle", [
                'title' => $title,
                'original_price' => $originalPrice,
                'discounted_price' => $price,
                'venta_id' => $ventaId
            ]);
            return null;
        }

        Log::info("Procesando tipo de envío dinámico", [
            'title' => $title,
            'original_price' => $originalPrice,
            'discounted_price' => $price,
            'discount' => $discount,
            'venta_id' => $ventaId,
            'empresa_id' => $empresaId
        ]);

        // Buscar o crear el producto de envío dinámicamente
        $productoEnvio = $this->buscarOCrearProductoEnvio($title, $price, $empresaId, $usuarioId, $sucursalId);

        if (!$productoEnvio) {
            Log::error("No se pudo crear/encontrar producto de envío", [
                'title' => $title,
                'empresa_id' => $empresaId
            ]);
            return null;
        }

        // Calcular precios originales y con descuento usando el servicio de impuestos
        $precioOriginalConIVA = $originalPrice; // Precio original con IVA
        $precioOriginalSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($precioOriginalConIVA, $empresaId);
        $ivaOriginal = $precioOriginalConIVA - $precioOriginalSinIVA;

        $precioConDescuentoConIVA = $price; // Precio con descuento con IVA
        $precioConDescuentoSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($precioConDescuentoConIVA, $empresaId);
        $ivaConDescuento = $precioConDescuentoConIVA - $precioConDescuentoSinIVA;

        // Calcular descuento sin IVA
        $descuentoSinIVA = $precioOriginalSinIVA - $precioConDescuentoSinIVA;

        // Crear el detalle de venta para el envío
        $detalleEnvio = Detalle::create([
            'id_venta' => $ventaId,
            'id_producto' => $productoEnvio->id,
            'descripcion' => $title, // Usar el título exacto de Shopify
            'cantidad' => 1,
            'precio' => $precioOriginalSinIVA, // Precio original sin IVA
            'precio_sin_iva' => $precioConDescuentoSinIVA, // Precio sin IVA (con descuento aplicado)
            'precio_con_iva' => $precioConDescuentoConIVA, // Precio con IVA (con descuento aplicado)
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
            'titulo_envio' => $title,
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
     * Busca o crea el producto de envío de forma dinámica
     *
     * @param string $title Título del envío desde Shopify
     * @param float $price Precio del envío (con IVA)
     * @param int $empresaId ID de la empresa
     * @param int $usuarioId ID del usuario
     * @param int $sucursalId ID de la sucursal
     * @return Producto|null Producto de envío
     */
    private function buscarOCrearProductoEnvio(string $title, float $price, int $empresaId, int $usuarioId, int $sucursalId): ?Producto
    {
        // Buscar producto existente por nombre exacto en la categoría "envios"
        $producto = Producto::where('nombre', $title)
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
                'precio_producto' => $producto->precio,
                'precio_shopify' => $price
            ]);

            // Actualizar el precio del producto si es diferente (opcional)
            $precioSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($price, $empresaId);
            if (abs($producto->precio - $precioSinIVA) > 0.01) {
                $producto->update(['precio' => $precioSinIVA]);
                Log::info("Precio de producto de envío actualizado", [
                    'producto_id' => $producto->id,
                    'precio_anterior' => $producto->precio,
                    'precio_nuevo' => $precioSinIVA
                ]);
            }

            return $producto;
        }

        // Si no existe, crear el producto de envío automáticamente
        Log::info("Producto de envío no encontrado, creando nuevo", [
            'nombre' => $title,
            'empresa_id' => $empresaId,
            'precio_con_iva' => $price
        ]);

        // Obtener o crear la categoría "envios"
        $categoria = Categoria::firstOrCreate(
            [
                'nombre' => 'envios',
                'id_empresa' => $empresaId
            ],
            [
                'descripcion' => 'Categoría para servicios de envío desde Shopify',
                'enable' => 1
            ]
        );

        // Calcular precio sin IVA
        $precioSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($price, $empresaId);

        // Crear el producto de envío
        $producto = Producto::create([
            'nombre' => $title,
            'descripcion' => 'Servicio de envío desde Shopify: ' . $title,
            'codigo' => 'ENVIO-' . strtoupper(substr(md5($title), 0, 8)),
            'tipo' => 'Servicio',
            'precio' => $precioSinIVA,
            'costo' => 0,
            'id_categoria' => $categoria->id,
            'id_empresa' => $empresaId,
            'id_usuario' => $usuarioId,
            'id_sucursal' => $sucursalId,
            'enable' => 1,
            'control_stock' => 0 // Los servicios de envío no controlan stock
        ]);

        Log::info("Producto de envío creado automáticamente", [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'precio_sin_iva' => $precioSinIVA,
            'precio_con_iva' => $price,
            'categoria_id' => $categoria->id
        ]);

        return $producto;
    }
}
