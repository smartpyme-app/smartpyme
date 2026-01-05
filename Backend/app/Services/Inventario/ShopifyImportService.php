<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use App\Services\ShopifyTransformer;
use App\Services\Inventario\ProductoService;
use Illuminate\Support\Facades\Log;

class ShopifyImportService
{
    protected $shopifyTransformer;
    protected $productoService;

    public function __construct(
        ShopifyTransformer $shopifyTransformer,
        ProductoService $productoService
    ) {
        $this->shopifyTransformer = $shopifyTransformer;
        $this->productoService = $productoService;
    }

    /**
     * Importa productos desde Shopify
     *
     * @param array $requestData
     * @return array
     */
    public function importarProductos(array $requestData): array
    {
        try {
            // Verificar si ya se realizó una importación exitosa
            $empresa = Empresa::find($requestData['id_empresa']);
            if ($empresa && $empresa->importacion_productos_shopify) {
                return [
                    'success' => false,
                    'mensaje' => 'Ya se realizó una importación exitosa de productos desde Shopify. No se puede volver a importar para evitar duplicados.',
                    'codigo_error' => 'IMPORTACION_YA_REALIZADA'
                ];
            }

            // Extraer el nombre de la tienda de la URL
            $storeUrl = $requestData['shopify_store_url'];
            $storeName = $this->extraerNombreTienda($storeUrl);

            if (!$storeName) {
                return [
                    'success' => false,
                    'mensaje' => 'URL de Shopify inválida'
                ];
            }

            // Construir URL base de la API de Shopify
            $baseUrl = "https://{$storeName}.myshopify.com/admin/api/2024-10/products.json";

            Log::info('=== INICIANDO IMPORTACIÓN DESDE SHOPIFY ===', [
                'store_url' => $storeUrl,
                'store_name' => $storeName,
                'base_url' => $baseUrl,
                'id_empresa' => $requestData['id_empresa'],
                'id_usuario' => $requestData['id_usuario']
            ]);

            // Obtener TODOS los productos usando paginación
            $productosShopify = $this->obtenerTodosLosProductosDeShopify($baseUrl, $requestData['shopify_consumer_secret']);

            if (!$productosShopify) {
                Log::error('No se pudieron obtener productos de Shopify', [
                    'base_url' => $baseUrl,
                    'store_name' => $storeName
                ]);
                return [
                    'success' => false,
                    'mensaje' => 'No se pudieron obtener los productos de Shopify. Verifica las credenciales.'
                ];
            }

            Log::info('Productos obtenidos de Shopify', [
                'total_productos' => count($productosShopify),
                'productos_ids' => array_column($productosShopify, 'id')
            ]);

            // Crear trabajos pendientes para cada producto
            $trabajosCreados = 0;
            foreach ($productosShopify as $productoShopify) {
                $this->crearTrabajoProducto($productoShopify, $requestData);
                $trabajosCreados++;
            }

            return [
                'success' => true,
                'mensaje' => 'Trabajos de importación creados exitosamente',
                'total_productos_shopify' => count($productosShopify),
                'trabajos_creados' => $trabajosCreados,
                'siguiente_paso' => 'Ejecutar comando: php artisan shopify:procesar-trabajos --lote=10 --procesar-productos-shopify',
                'instrucciones' => [
                    '1. Los trabajos están guardados en la base de datos',
                    '2. Puedes procesarlos cuando quieras con el comando',
                    '3. Cada ejecución del comando procesa 10 productos',
                    '4. Repite el comando hasta completar todos los productos'
                ],
                'resumen' => [
                    'productos_originales_shopify' => count($productosShopify),
                    'trabajos_creados' => $trabajosCreados,
                    'fecha_creacion_trabajos' => now()->format('Y-m-d H:i:s'),
                    'estado' => 'trabajos_creados'
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error al importar productos desde Shopify: ' . $e->getMessage());
            return [
                'success' => false,
                'mensaje' => 'Error al importar productos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extrae el nombre de la tienda de una URL de Shopify
     *
     * @param string $url
     * @return string|null
     */
    public function extraerNombreTienda(string $url): ?string
    {
        // Extraer nombre de tienda de URLs como: https://1em3xk-pb.myshopify.com/
        if (preg_match('/https?:\/\/([^\.]+)\.myshopify\.com/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Obtiene todos los productos de Shopify usando paginación
     *
     * @param string $baseUrl
     * @param string $accessToken
     * @return array|null
     */
    public function obtenerTodosLosProductosDeShopify(string $baseUrl, string $accessToken): ?array
    {
        $todosLosProductos = [];
        $pageInfo = null;
        $pagina = 1;

        Log::info('Iniciando obtención de todos los productos con paginación');

        do {
            // Construir URL con parámetros de paginación
            $url = $baseUrl . '?limit=250'; // Máximo permitido por Shopify
            if ($pageInfo) {
                $url .= '&page_info=' . $pageInfo;
            }

            Log::info("Obteniendo página {$pagina} de productos", [
                'url' => $url,
                'page_info' => $pageInfo
            ]);

            $resultado = $this->obtenerProductosDeShopifyConPaginacion($url, $accessToken);

            if (!$resultado || !isset($resultado['productos'])) {
                Log::error("Error al obtener página {$pagina}");
                break;
            }

            $productosPagina = $resultado['productos'];
            $todosLosProductos = array_merge($todosLosProductos, $productosPagina);

            Log::info("Página {$pagina} obtenida", [
                'productos_en_pagina' => count($productosPagina),
                'total_acumulado' => count($todosLosProductos),
                'next_page_info' => $resultado['next_page_info'] ?? 'null'
            ]);

            // Obtener el page_info para la siguiente página
            $pageInfo = $resultado['next_page_info'] ?? null;

            // Si no hay next_page_info, es la última página
            if (!$pageInfo) {
                Log::info('Última página alcanzada (no hay next_page_info)');
                break;
            }

            $pagina++;

            // Prevenir bucles infinitos (máximo 100 páginas = 25,000 productos)
            if ($pagina > 100) {
                Log::warning('Límite de páginas alcanzado (100 páginas)');
                break;
            }

        } while (true);

        Log::info('Obtención de productos completada', [
            'total_paginas' => $pagina - 1,
            'total_productos' => count($todosLosProductos)
        ]);

        return $todosLosProductos;
    }

    /**
     * Obtiene productos de Shopify con paginación
     *
     * @param string $apiUrl
     * @param string $accessToken
     * @return array|null
     */
    public function obtenerProductosDeShopifyConPaginacion(string $apiUrl, string $accessToken): ?array
    {
        try {
            Log::info('Haciendo petición a Shopify API con paginación', [
                'url' => $apiUrl,
                'access_token_length' => strlen($accessToken)
            ]);

            $headers = [
                'X-Shopify-Access-Token: ' . $accessToken,
                'Content-Type: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HEADER, true); // Incluir headers en la respuesta

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Separar headers del body
            $headerString = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            Log::info('Respuesta de Shopify API con paginación', [
                'http_code' => $httpCode,
                'response_length' => strlen($body),
                'curl_error' => $curlError
            ]);

            if ($httpCode !== 200) {
                Log::error("Error en petición a Shopify. HTTP Code: {$httpCode}, Response: {$body}");
                return null;
            }

            $data = json_decode($body, true);
            $products = $data['products'] ?? [];

            // Extraer el page_info del header Link
            $nextPageInfo = $this->extraerNextPageInfo($headerString);

            Log::info('Productos parseados de Shopify con paginación', [
                'total_products' => count($products),
                'next_page_info' => $nextPageInfo,
                'first_product_id' => $products[0]['id'] ?? 'N/A'
            ]);

            return [
                'productos' => $products,
                'next_page_info' => $nextPageInfo
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener productos de Shopify con paginación: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrae la información de paginación (next_page_info) del header Link
     *
     * @param string $headerString
     * @return string|null
     */
    public function extraerNextPageInfo(string $headerString): ?string
    {
        // Buscar el header Link que contiene la información de paginación
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $headerString, $matches)) {
            $nextUrl = $matches[1];
            // Extraer el page_info del URL
            if (preg_match('/[?&]page_info=([^&]+)/', $nextUrl, $pageMatches)) {
                return urldecode($pageMatches[1]);
            }
        }
        return null;
    }

    /**
     * Obtiene productos de Shopify (método simple sin paginación)
     *
     * @param string $apiUrl
     * @param string $accessToken
     * @return array|null
     */
    public function obtenerProductosDeShopify(string $apiUrl, string $accessToken): ?array
    {
        try {
            Log::info('Haciendo petición a Shopify API', [
                'url' => $apiUrl,
                'access_token_length' => strlen($accessToken)
            ]);

            $headers = [
                'X-Shopify-Access-Token: ' . $accessToken,
                'Content-Type: application/json'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            Log::info('Respuesta de Shopify API', [
                'http_code' => $httpCode,
                'response_length' => strlen($response),
                'curl_error' => $curlError
            ]);

            if ($httpCode !== 200) {
                Log::error("Error en petición a Shopify. HTTP Code: {$httpCode}, Response: {$response}");
                return null;
            }

            $data = json_decode($response, true);
            $products = $data['products'] ?? [];

            Log::info('Productos parseados de Shopify', [
                'total_products' => count($products),
                'first_product_sample' => $products[0] ?? null
            ]);

            return $products;
        } catch (\Exception $e) {
            Log::error('Error al hacer petición a Shopify: ' . $e->getMessage(), [
                'exception_trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Procesa productos de Shopify en lotes
     *
     * @param array $productosShopify
     * @param int $idEmpresa
     * @param int $idUsuario
     * @param int $idSucursal
     * @param bool $incluirDrafts
     * @return array
     */
    public function procesarProductosShopify(
        array $productosShopify,
        int $idEmpresa,
        int $idUsuario,
        int $idSucursal,
        bool $incluirDrafts = false
    ): array {
        $productosImportados = 0;
        $productosData = [];

        Log::info('Iniciando procesamiento de productos Shopify', [
            'total_productos' => count($productosShopify),
            'id_empresa' => $idEmpresa,
            'id_usuario' => $idUsuario,
            'id_sucursal' => $idSucursal
        ]);

        // Procesar en lotes de 10 productos para Hostinger Shared
        $loteSize = 10;
        $productosShopifyChunks = array_chunk($productosShopify, $loteSize);
        $totalLotes = count($productosShopifyChunks);

        Log::info("Procesando en {$totalLotes} lotes de {$loteSize} productos cada uno");

        foreach ($productosShopifyChunks as $loteIndex => $loteProductos) {
            Log::info("Procesando lote " . ($loteIndex + 1) . " de {$totalLotes}", [
                'productos_en_lote' => count($loteProductos),
                'lote_actual' => $loteIndex + 1,
                'total_lotes' => $totalLotes
            ]);

            foreach ($loteProductos as $index => $productoShopify) {
                Log::info("Procesando producto Shopify #{$index}", [
                    'producto_id' => $productoShopify['id'],
                    'titulo' => $productoShopify['title'],
                    'variants_count' => count($productoShopify['variants'] ?? [])
                ]);

                // Transformar productos usando ShopifyTransformer
                $productosTransformados = $this->shopifyTransformer->transformarProductoDesdeShopify(
                    $productoShopify,
                    $idEmpresa,
                    $idUsuario,
                    $idSucursal,
                    $incluirDrafts,
                    true // Es importación masiva
                );

                Log::info("Productos transformados para producto #{$productoShopify['id']}", [
                    'variantes_transformadas' => count($productosTransformados),
                    'nombres_variantes' => array_column($productosTransformados, 'nombre')
                ]);

                foreach ($productosTransformados as $variantIndex => $productoData) {
                    try {
                        Log::info("Procesando variante #{$variantIndex}", [
                            'nombre' => $productoData['nombre'],
                            'precio' => $productoData['precio'],
                            'stock' => $productoData['_stock'],
                            'shopify_variant_id' => $productoData['shopify_variant_id']
                        ]);

                        // Preparar datos del producto
                        $productoFinal = $this->productoService->prepararDatos($productoData, $idEmpresa);

                        $productosData[] = [
                            'producto_original_shopify' => $productoShopify,
                            'producto_transformado' => $productoData,
                            'producto_final' => $productoFinal
                        ];

                        // Crear o actualizar producto
                        $producto = $this->productoService->crearOActualizar($productoData, $idEmpresa);
                        if ($producto) {
                            $this->crearInventarioProducto($producto->id, $productoData, $idEmpresa, $idUsuario);

                            // Crear job para procesar imágenes después
                            $this->crearJobImagenes($producto, $productoShopify, $productoData, $idEmpresa, $idUsuario);

                            $productosImportados++;

                            Log::info("Producto insertado exitosamente", [
                                'producto_id' => $producto->id,
                                'nombre' => $producto->nombre,
                                'precio' => $producto->precio,
                                'stock' => $productoData['_stock'] ?? 0
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Error al procesar producto de Shopify: " . $e->getMessage(), [
                            'producto_data' => $productoData,
                            'error_trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                // Pausa entre lotes para evitar timeout
                if ($loteIndex < $totalLotes - 1) {
                    Log::info("Pausa entre lotes para evitar timeout", [
                        'lote_completado' => $loteIndex + 1,
                        'productos_importados_hasta_ahora' => $productosImportados
                    ]);
                    sleep(2); // Pausa de 2 segundos entre lotes
                }
            }

            // Limpiar cache de categorías al finalizar la importación
            $cacheKey = "categoria_general_empresa_{$idEmpresa}";
            \Illuminate\Support\Facades\Cache::forget($cacheKey);

            Log::info('Procesamiento completado', [
                'total_productos_importados' => $productosImportados,
                'total_datos_capturados' => count($productosData)
            ]);

            return [
                'count' => $productosImportados
            ];
        }
    }

    /**
     * Crea inventario para un producto
     *
     * @param int $productoId
     * @param array $productoData
     * @param int $idEmpresa
     * @param int $idUsuario
     * @return void
     */
    private function crearInventarioProducto(int $productoId, array $productoData, int $idEmpresa, int $idUsuario): void
    {
        // Obtener la primera bodega activa de la empresa
        $bodega = Bodega::where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->first();

        if (!$bodega) {
            Log::warning("No se encontró bodega activa para la empresa {$idEmpresa}");
            return;
        }

        // Buscar inventario existente
        $inventario = Inventario::where('id_producto', $productoId)
            ->where('id_bodega', $bodega->id)
            ->first();

        if (!$inventario) {
            $inventario = new Inventario();
            $inventario->id_producto = $productoId;
            $inventario->id_bodega = $bodega->id;
        }

        // Establecer stock desde Shopify
        $stock = $productoData['_stock'] ?? 0;
        $inventario->stock = $stock;
        $inventario->save();

        // Crear ajuste de inventario
        if ($stock > 0) {
            $ajuste = Ajuste::create([
                'concepto' => 'Importación desde Shopify',
                'id_producto' => $productoId,
                'id_bodega' => $bodega->id,
                'stock_actual' => 0,
                'stock_real' => $stock,
                'ajuste' => $stock,
                'estado' => 'Confirmado',
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
            ]);

            $inventario->kardex($ajuste, $ajuste->ajuste);
        }
    }

    /**
     * Crea un trabajo pendiente para procesar imágenes
     *
     * @param \App\Models\Inventario\Producto $producto
     * @param array $productoShopify
     * @param array $productoData
     * @param int $idEmpresa
     * @param int $idUsuario
     * @return void
     */
    private function crearJobImagenes($producto, array $productoShopify, array $productoData, int $idEmpresa, int $idUsuario): void
    {
        try {
            // Verificar si el producto tiene imágenes
            if (empty($productoShopify['images']) || !is_array($productoShopify['images'])) {
                Log::info("Producto sin imágenes - no se crea job", [
                    'producto_id' => $producto->id,
                    'nombre' => $producto->nombre
                ]);
                return;
            }

            $shopifyVariantId = $productoData['shopify_variant_id'] ?? null;

            // Filtrar imágenes que pertenecen a esta variante específica
            $imagenesVariante = $this->filtrarImagenesPorVariante($productoShopify['images'], $shopifyVariantId);

            if (empty($imagenesVariante)) {
                Log::info("Variante sin imágenes específicas - no se crea job", [
                    'producto_id' => $producto->id,
                    'shopify_variant_id' => $shopifyVariantId
                ]);
                return;
            }

            // Crear trabajo pendiente para procesar imágenes
            $trabajo = new \App\Models\TrabajosPendientes();
            $trabajo->tipo = 'procesar_imagenes_shopify';
            $trabajo->parametros = json_encode([
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'shopify_variant_id' => $shopifyVariantId,
                'shopify_product_id' => $productoShopify['id'],
                'imagenes' => $imagenesVariante,
                'total_imagenes' => count($imagenesVariante)
            ]);
            $trabajo->estado = 'pendiente';
            $trabajo->fecha_creacion = now();
            $trabajo->id_usuario = $idUsuario;
            $trabajo->id_empresa = $idEmpresa;
            $trabajo->save();

            Log::info("Job de imágenes creado exitosamente", [
                'trabajo_id' => $trabajo->id,
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'total_imagenes' => count($imagenesVariante),
                'estado' => 'pendiente'
            ]);
        } catch (\Exception $e) {
            Log::error("Error creando job de imágenes: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Crea un trabajo pendiente para un producto
     *
     * @param array $productoShopify
     * @param array $requestData
     * @return void
     */
    public function crearTrabajoProducto(array $productoShopify, array $requestData): void
    {
        try {
            $trabajo = new \App\Models\TrabajosPendientes();
            $trabajo->tipo = 'shopify_import_producto';
            $trabajo->estado = 'pendiente';
            $trabajo->prioridad = 1;
            $trabajo->parametros = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $requestData['id_empresa'],
                'id_usuario' => $requestData['id_usuario'],
                'id_sucursal' => $requestData['id_sucursal'],
                'shopify_store_url' => $requestData['shopify_store_url'],
                'shopify_consumer_secret' => $requestData['shopify_consumer_secret'],
                'shopify_consumer_key' => $requestData['shopify_consumer_key'] ?? null
            ]);
            $trabajo->datos = json_encode([
                'producto_shopify' => $productoShopify,
                'id_empresa' => $requestData['id_empresa'],
                'id_usuario' => $requestData['id_usuario'],
                'id_sucursal' => $requestData['id_sucursal'],
                'shopify_store_url' => $requestData['shopify_store_url'],
                'shopify_consumer_secret' => $requestData['shopify_consumer_secret'],
                'shopify_consumer_key' => $requestData['shopify_consumer_key'] ?? null
            ]);
            $trabajo->intentos = 0;
            $trabajo->max_intentos = 3;
            $trabajo->fecha_creacion = now();
            $trabajo->fecha_procesamiento = null;
            $trabajo->id_usuario = $requestData['id_usuario'];
            $trabajo->id_empresa = $requestData['id_empresa'];
            $trabajo->save();

            Log::info("Trabajo creado para producto Shopify", [
                'trabajo_id' => $trabajo->id,
                'producto_id' => $productoShopify['id'],
                'titulo' => $productoShopify['title']
            ]);
        } catch (\Exception $e) {
            Log::error("Error creando trabajo para producto", [
                'producto_id' => $productoShopify['id'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Filtra imágenes que pertenecen a una variante específica
     *
     * @param array $imagenes
     * @param int|null $shopifyVariantId
     * @return array
     */
    private function filtrarImagenesPorVariante(array $imagenes, ?int $shopifyVariantId): array
    {
        $imagenesVariante = [];

        foreach ($imagenes as $imagen) {
            $variantIds = $imagen['variant_ids'] ?? [];

            // Si la imagen no tiene variant_ids específicos, es imagen general del producto
            // Si tiene variant_ids, verificar si incluye nuestra variante
            if (empty($variantIds) || in_array($shopifyVariantId, $variantIds)) {
                $imagenesVariante[] = $imagen;

                Log::info("Imagen asignada a variante", [
                    'shopify_variant_id' => $shopifyVariantId,
                    'imagen_id' => $imagen['id'] ?? 'N/A',
                    'variant_ids_imagen' => $variantIds,
                    'es_imagen_general' => empty($variantIds)
                ]);
            }
        }

        return $imagenesVariante;
    }
}

