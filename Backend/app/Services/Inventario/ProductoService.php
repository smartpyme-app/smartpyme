<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Producto;
use App\Services\Inventario\CategoriaService;
use App\Services\ImpuestosService;
use Illuminate\Support\Facades\Log;

class ProductoService
{
    protected $categoriaService;
    protected $impuestosService;

    public function __construct(
        CategoriaService $categoriaService,
        ImpuestosService $impuestosService
    ) {
        $this->categoriaService = $categoriaService;
        $this->impuestosService = $impuestosService;
    }

    /**
     * Prepara los datos de un producto para inserción
     *
     * @param array $productoData
     * @param int $idEmpresa
     * @return array
     */
    public function prepararDatos(array $productoData, int $idEmpresa): array
    {
        $categoria = $this->categoriaService->obtenerOCrear($productoData, $idEmpresa);

        return [
            'nombre' => $productoData['nombre'],
            'descripcion' => $productoData['descripcion'] ?? '',
            'codigo' => $productoData['codigo'] ?? '',
            'barcode' => $productoData['barcode'] ?? '',
            'precio' => $productoData['precio'] ?? 0,
            'costo' => $productoData['costo'] ?? 0,
            'costo_promedio' => $productoData['costo'] ?? 0,
            'id_categoria' => $categoria->id,
            'categoria_nombre' => $categoria->nombre,
            'id_empresa' => $idEmpresa,
            'enable' => true,
            'tipo' => 'Producto',
            'shopify_product_id' => $productoData['shopify_product_id'] ?? null,
            'shopify_variant_id' => $productoData['shopify_variant_id'] ?? null,
            'shopify_inventory_item_id' => $productoData['shopify_inventory_item_id'] ?? null,
            'stock_inicial' => $productoData['_stock'] ?? 0
        ];
    }

    /**
     * Crea o actualiza un producto
     *
     * @param array $productoData
     * @param int $idEmpresa
     * @return Producto|null
     */
    public function crearOActualizar(array $productoData, int $idEmpresa): ?Producto
    {
        // Búsqueda más robusta para prevenir duplicados
        $producto = $this->buscarExistente($productoData, $idEmpresa);

        $esNuevo = !$producto;
        if ($esNuevo) {
            $producto = new Producto();
            Log::info("Creando nuevo producto", [
                'nombre' => $productoData['nombre'],
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A',
                'shopify_product_id' => $productoData['shopify_product_id'] ?? 'N/A'
            ]);
        } else {
            Log::info("Actualizando producto existente", [
                'producto_id' => $producto->id,
                'nombre_anterior' => $producto->nombre,
                'nombre_nuevo' => $productoData['nombre'],
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A'
            ]);
        }

        // Obtener o crear categoría
        $categoria = $this->categoriaService->obtenerOCrear($productoData, $idEmpresa);

        // Llenar datos del producto
        $producto->nombre = $productoData['nombre'];
        $producto->nombre_variante = $productoData['nombre_variante'] ?? null;
        $producto->descripcion = $productoData['descripcion'] ?? '';
        $producto->codigo = $productoData['codigo'] ?? '';
        $producto->barcode = $productoData['barcode'] ?? '';

        // IMPORTANTE: Los precios de Shopify ya incluyen IVA
        $precioShopify = $productoData['precio'] ?? 0;
        $precioSinIVA = $this->calcularPrecioSinIVA($precioShopify, $idEmpresa);
        $producto->precio = $precioSinIVA;

        Log::info("Precio procesado para producto", [
            'producto_id' => $producto->id ?? 'nuevo',
            'nombre' => $producto->nombre,
            'precio_shopify_con_iva' => $precioShopify,
            'precio_guardado_sin_iva' => $precioSinIVA,
            'diferencia_iva' => round($precioShopify - $precioSinIVA, 2)
        ]);

        $producto->costo = $productoData['costo'] ?? 0;
        $producto->costo_promedio = $productoData['costo'] ?? 0;
        $producto->id_categoria = $categoria->id;
        $producto->id_empresa = $idEmpresa;
        $producto->enable = true;
        $producto->tipo = 'Producto';

        // IMPORTANTE: Marcar que este producto viene de Shopify ANTES de guardar para evitar sincronización de vuelta
        $producto->syncing_from_shopify = true;
        $producto->last_shopify_sync = now();

        // Campos específicos de Shopify
        $producto->shopify_product_id = $productoData['shopify_product_id'] ?? null;
        $producto->shopify_variant_id = $productoData['shopify_variant_id'] ?? null;
        $producto->shopify_inventory_item_id = $productoData['shopify_inventory_item_id'] ?? null;

        $producto->save();

        Log::info($esNuevo ? "Producto creado exitosamente" : "Producto actualizado exitosamente", [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'nombre_variante' => $producto->nombre_variante,
            'precio' => $producto->precio,
            'costo' => $producto->costo
        ]);

        return $producto;
    }

    /**
     * Búsqueda robusta de productos existentes para prevenir duplicados
     *
     * @param array $productoData
     * @param int $idEmpresa
     * @return Producto|null
     */
    public function buscarExistente(array $productoData, int $idEmpresa): ?Producto
    {
        // 1. Buscar por shopify_variant_id (más confiable)
        if (!empty($productoData['shopify_variant_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('shopify_variant_id', $productoData['shopify_variant_id'])
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por shopify_variant_id", [
                    'producto_id' => $producto->id,
                    'shopify_variant_id' => $productoData['shopify_variant_id'],
                    'nombre' => $producto->nombre
                ]);
                return $producto;
            }
        }

        // 2. Buscar por shopify_product_id (para productos sin variantes)
        if (!empty($productoData['shopify_product_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('shopify_product_id', $productoData['shopify_product_id'])
                ->whereNull('shopify_variant_id') // Solo productos sin variantes
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por shopify_product_id", [
                    'producto_id' => $producto->id,
                    'shopify_product_id' => $productoData['shopify_product_id'],
                    'nombre' => $producto->nombre
                ]);
                return $producto;
            }
        }

        // 3. Buscar por nombre exacto + empresa (último recurso) - SOLO para productos sin variantes
        if (empty($productoData['shopify_variant_id'])) {
            $producto = Producto::where('id_empresa', $idEmpresa)
                ->where('nombre', $productoData['nombre'])
                ->where('tipo', 'Producto')
                ->whereNull('shopify_variant_id') // Solo productos sin variantes
                ->first();

            if ($producto) {
                Log::info("Producto encontrado por nombre exacto (sin variantes)", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre,
                    'shopify_variant_id_actual' => $producto->shopify_variant_id,
                    'shopify_variant_id_nuevo' => $productoData['shopify_variant_id'] ?? 'N/A'
                ]);
                return $producto;
            }
        }

        // 4. Verificar duplicados potenciales por nombre similar
        $productosSimilares = Producto::where('id_empresa', $idEmpresa)
            ->where('nombre', 'like', '%' . $productoData['nombre'] . '%')
            ->where('tipo', 'Producto')
            ->get();

        if ($productosSimilares->count() > 0) {
            Log::warning("Productos similares encontrados - posible duplicado", [
                'nombre_buscado' => $productoData['nombre'],
                'productos_similares' => $productosSimilares->pluck('nombre')->toArray(),
                'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A'
            ]);
        }

        Log::info("No se encontró producto existente - será creado como nuevo", [
            'nombre' => $productoData['nombre'],
            'shopify_variant_id' => $productoData['shopify_variant_id'] ?? 'N/A',
            'shopify_product_id' => $productoData['shopify_product_id'] ?? 'N/A'
        ]);

        return null;
    }

    /**
     * Calcular precio sin IVA desde precio con IVA incluido
     *
     * @param float $precioConIVA
     * @param int $idEmpresa
     * @return float
     */
    public function calcularPrecioSinIVA(float $precioConIVA, int $idEmpresa): float
    {
        try {
            // Validar precio de entrada
            if (empty($precioConIVA) || $precioConIVA <= 0) {
                Log::warning("Precio inválido recibido", [
                    'precio_con_iva' => $precioConIVA,
                    'id_empresa' => $idEmpresa
                ]);
                return 0;
            }

            // Obtener configuración de IVA de la empresa
            $empresa = \App\Models\Admin\Empresa::find($idEmpresa);

            if (!$empresa) {
                Log::warning("Empresa no encontrada", [
                    'id_empresa' => $idEmpresa,
                    'precio_original' => $precioConIVA
                ]);
                return $precioConIVA;
            }

            // Verificar si la empresa cobra IVA
            if ($empresa->cobra_iva !== 'Si' || empty($empresa->iva) || $empresa->iva <= 0) {
                Log::info("Empresa no cobra IVA - precio sin modificar", [
                    'id_empresa' => $idEmpresa,
                    'empresa_cobra_iva' => $empresa->cobra_iva,
                    'porcentaje_iva_empresa' => $empresa->iva,
                    'precio_original' => $precioConIVA,
                    'precio_final' => $precioConIVA
                ]);
                return $precioConIVA;
            }

            // Usar ImpuestosService para calcular precio sin IVA
            $precioSinIVA = $this->impuestosService->calcularPrecioSinImpuesto($precioConIVA, $idEmpresa);
            $ivaDescontado = $precioConIVA - $precioSinIVA;

            Log::info("Precio calculado sin IVA exitosamente", [
                'id_empresa' => $idEmpresa,
                'empresa_nombre' => $empresa->nombre ?? 'N/A',
                'precio_con_iva' => $precioConIVA,
                'porcentaje_iva' => $empresa->iva,
                'precio_sin_iva' => round($precioSinIVA, 2),
                'iva_descontado' => round($ivaDescontado, 2)
            ]);

            return round($precioSinIVA, 2);
        } catch (\Exception $e) {
            Log::error("Error calculando precio sin IVA: " . $e->getMessage(), [
                'precio_con_iva' => $precioConIVA,
                'id_empresa' => $idEmpresa,
                'error_trace' => $e->getTraceAsString()
            ]);

            // En caso de error, devolver el precio original
            return $precioConIVA;
        }
    }
}

