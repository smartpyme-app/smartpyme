<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrabajosPendientes;
use App\Services\ShopifyTransformer;
use App\Services\ImpuestosService;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Categorias\Categoria;
use Illuminate\Support\Facades\Log;

class ImportarShopifyCommand extends Command
{
    protected $signature = 'shopify:procesar-trabajos {--lote=10} {--procesar-productos-shopify}';
    protected $description = 'Procesar trabajos pendientes de importación Shopify';

    protected $transformer;

    public function __construct(ShopifyTransformer $transformer)
    {
        parent::__construct();
        $this->transformer = $transformer;
    }

    public function handle()
    {
        $loteSize = $this->option('lote');
        $procesarProductosShopify = $this->option('procesar-productos-shopify');
        
        $this->info("Iniciando procesamiento de trabajos Shopify...");
        
        // Construir query base
        $query = TrabajosPendientes::where('estado', 'pendiente')
            ->orderBy('prioridad', 'desc')
            ->orderBy('fecha_creacion', 'asc')
            ->limit($loteSize);

        // Si se especifica la opción, solo procesar productos de Shopify
        if ($procesarProductosShopify) {
            $query->where('tipo', 'shopify_import_producto');
            $this->info("Procesando SOLO trabajos de productos Shopify...");
        } else {
            $this->info("Procesando TODOS los trabajos pendientes...");
        }
        
        $trabajos = $query->get();

        if ($trabajos->isEmpty()) {
            $this->info("No hay trabajos pendientes para procesar");
            return;
        }

        $this->info("Procesando {$trabajos->count()} trabajos...");

        $procesados = 0;
        $errores = 0;

        foreach ($trabajos as $trabajo) {
            try {
                $this->procesarTrabajo($trabajo);
                $procesados++;
                $this->info("Trabajo {$trabajo->id} procesado exitosamente");
            } catch (\Exception $e) {
                $errores++;
                $this->error("Error procesando trabajo {$trabajo->id}: " . $e->getMessage());
                
                // Incrementar intentos
                $trabajo->intentos++;
                if ($trabajo->intentos >= $trabajo->max_intentos) {
                    $trabajo->estado = 'fallido';
                } else {
                    $trabajo->estado = 'pendiente'; // Volver a pendiente para reintentar
                }
                $trabajo->save();
            }
        }

        $this->info("Procesamiento completado: {$procesados} exitosos, {$errores} errores");
    }

    private function procesarTrabajo($trabajo)
    {
        $datos = json_decode($trabajo->datos, true);
        
        // Marcar como procesando
        $trabajo->estado = 'procesando';
        $trabajo->fecha_procesamiento = now();
        $trabajo->save();

        try {
            // Procesar según el tipo de trabajo
            switch ($trabajo->tipo) {
                case 'shopify_import_producto':
                    $this->procesarProductoShopify($datos);
                    break;
                default:
                    throw new \Exception("Tipo de trabajo no soportado: {$trabajo->tipo}");
            }

            // Marcar como completado
            $trabajo->estado = 'completado';
            $trabajo->save();

        } catch (\Exception $e) {
            // Marcar como fallido
            $trabajo->estado = 'fallido';
            $trabajo->save();
            throw $e;
        }
    }

    private function procesarProductoShopify($datos)
    {
        $this->info("Procesando producto Shopify: " . ($datos['producto_shopify']['title'] ?? 'N/A'));

        // Transformar producto usando ShopifyTransformer
        $productosTransformados = $this->transformer->transformarProductoDesdeShopify(
            $datos['producto_shopify'],
            $datos['id_empresa'],
            $datos['id_usuario'],
            $datos['id_sucursal'],
            true, // incluirDrafts
            false // NO es importación masiva
        );

        $this->info("Productos transformados: " . count($productosTransformados));

        foreach ($productosTransformados as $productoData) {
            $this->info("Procesando variante: " . $productoData['nombre'] . " (tiene imagen: " . (!empty($productoData['imagen_url']) ? 'Sí' : 'No') . ")");
            
            // Crear producto
            $producto = $this->crearProducto($productoData, $datos);
            
            if ($producto) {
                // Crear inventario
                $this->crearInventario($producto->id, $productoData, $datos);
            }
        }
    }

    private function crearProducto($productoData, $datos)
    {
        // Buscar producto existente por shopify_variant_id
        $productoExistente = Producto::where('shopify_variant_id', $productoData['shopify_variant_id'])
            ->where('id_empresa', $datos['id_empresa'])
            ->first();

        if ($productoExistente) {
            $this->info("Producto ya existe, actualizando: {$productoExistente->nombre} - {$productoExistente->nombre_variante}");
            
            // Actualizar datos del producto existente
            $productoExistente->nombre = $productoData['nombre'];
            $productoExistente->nombre_variante = $productoData['nombre_variante'] ?? null;
            $productoExistente->descripcion = $productoData['descripcion'] ?? '';
            $productoExistente->precio = $productoData['precio'] ?? 0;
            $productoExistente->costo = $productoData['costo'] ?? 0;
            $productoExistente->codigo = $productoData['codigo'] ?? '';
            $productoExistente->barcode = $productoData['barcode'] ?? '';
            $productoExistente->last_shopify_sync = now();
            $productoExistente->save();
            
            // Descargar y guardar imagen del producto (también para productos existentes)
            $this->info("Llamando descargarYGuardarImagen para producto existente: {$productoExistente->nombre}");
            $this->descargarYGuardarImagen($productoExistente, $productoData);
            
            return $productoExistente;
        }

        // Crear nuevo producto
        $producto = new Producto();
        $producto->nombre = $productoData['nombre'];
        $producto->nombre_variante = $productoData['nombre_variante'] ?? null;
        $producto->descripcion = $productoData['descripcion'] ?? '';
        $producto->precio = $productoData['precio'] ?? 0;
        $producto->costo = $productoData['costo'] ?? 0;
        $producto->codigo = $productoData['codigo'] ?? '';
        $producto->barcode = $productoData['barcode'] ?? '';
        $producto->id_empresa = $datos['id_empresa'];
        $producto->id_categoria = $this->obtenerOCrearCategoria($datos['id_empresa'])->id;
        $producto->tipo = 'Producto';
        $producto->enable = true;
        
        // Campos específicos de Shopify
        $producto->shopify_product_id = $productoData['shopify_product_id'] ?? null;
        $producto->shopify_variant_id = $productoData['shopify_variant_id'] ?? null;
        $producto->shopify_inventory_item_id = $productoData['shopify_inventory_item_id'] ?? null;
        $producto->last_shopify_sync = now();
        
        $producto->save();
        
        // Descargar y guardar imagen del producto
        $this->descargarYGuardarImagen($producto, $productoData);
        
        $this->info("Producto creado: {$producto->nombre} - {$producto->nombre_variante}");
        
        return $producto;
    }

    private function obtenerOCrearCategoria($idEmpresa)
    {
        $categoria = Categoria::where('nombre', 'General')
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$categoria) {
            $categoria = new Categoria();
            $categoria->nombre = 'General';
            $categoria->descripcion = 'Categoría general para productos importados';
            $categoria->enable = true;
            $categoria->id_empresa = $idEmpresa;
            $categoria->save();
        }

        return $categoria;
    }

    private function crearInventario($productoId, $productoData, $datos)
    {
        $bodega = Bodega::where('id_empresa', $datos['id_empresa'])
            ->where('activo', true)
            ->first();

        if (!$bodega) {
            throw new \Exception("No se encontró bodega activa para la empresa {$datos['id_empresa']}");
        }

        // Buscar inventario existente
        $inventarioExistente = Inventario::where('id_producto', $productoId)
            ->where('id_bodega', $bodega->id)
            ->first();

        if ($inventarioExistente) {
            $this->info("Inventario ya existe, actualizando stock: {$inventarioExistente->stock} -> " . ($productoData['_stock'] ?? 0));
            $inventarioExistente->stock = $productoData['_stock'] ?? 0;
            $inventarioExistente->save();
            return;
        }

        // Crear nuevo inventario
        $inventario = new Inventario();
        $inventario->id_producto = $productoId;
        $inventario->id_bodega = $bodega->id;
        $inventario->stock = $productoData['_stock'] ?? 0;
        $inventario->stock_minimo = 0;
        $inventario->stock_maximo = 1000;
        $inventario->save();
        
        $this->info("Inventario creado para producto {$productoId} con stock: " . ($productoData['_stock'] ?? 0));
    }

    private function descargarYGuardarImagen($producto, $productoData)
    {
        try {
            // Obtener la primera imagen del producto
            $imagenUrl = $productoData['imagen_url'] ?? null;
            $shopifyImageId = $productoData['shopify_image_id'] ?? null;
            
            if (!$imagenUrl) {
                $this->info("No hay imagen disponible para el producto: {$producto->nombre}");
                return;
            }

            // Verificar si el producto ya tiene imágenes
            $imagenesExistentes = $producto->imagenes;
            
            if ($imagenesExistentes->count() > 0) {
                $this->info("Producto ya tiene {$imagenesExistentes->count()} imagen(es), verificando si necesita actualización");
                
                // Verificar si ya existe una imagen con el mismo shopify_image_id
                $imagenExistenteConMismoId = null;
                if ($shopifyImageId) {
                    $imagenExistenteConMismoId = $imagenesExistentes->where('shopify_image_id', $shopifyImageId)->first();
                }
                
                if ($imagenExistenteConMismoId) {
                    $this->info("Imagen con el mismo shopify_image_id ya existe, omitiendo descarga para producto: {$producto->nombre}");
                    return;
                }
                
                $this->info("Imagen diferente detectada, reemplazando todas las imágenes existentes");
                
                // Eliminar TODAS las imágenes existentes
                foreach ($imagenesExistentes as $imagenExistente) {
                    // Eliminar archivo físico existente
                    $rutaImagenExistente = public_path('img' . $imagenExistente->img);
                    if (file_exists($rutaImagenExistente)) {
                        unlink($rutaImagenExistente);
                        $this->info("Archivo de imagen anterior eliminado: {$rutaImagenExistente}");
                    }
                    
                    // Eliminar registro de la base de datos
                    $imagenExistente->delete();
                }
                
                $this->info("Todas las imágenes anteriores eliminadas de la base de datos");
                
                // Continuar con la descarga de la nueva imagen
            }

            $this->info("Descargando nueva imagen para producto: {$producto->nombre}");
            $this->info("URL de imagen: {$imagenUrl}");

            // Crear directorio para las imágenes del producto
            $directorioImagenes = public_path('img/productos');
            if (!file_exists($directorioImagenes)) {
                mkdir($directorioImagenes, 0755, true);
            }

            // Generar nombre único para la imagen
            $extension = pathinfo(parse_url($imagenUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg'; // Default extension
            }
            
            $nombreImagen = 'producto_' . $producto->id . '_' . time() . '.' . $extension;
            $rutaCompleta = $directorioImagenes . '/' . $nombreImagen;

            // Descargar la imagen usando cURL para mejor control
            $imagenContenido = $this->descargarImagenDesdeUrl($imagenUrl);
            if (!$imagenContenido) {
                $this->error("No se pudo descargar la imagen para el producto: {$producto->nombre}");
                return;
            }

            // Guardar la imagen
            if (file_put_contents($rutaCompleta, $imagenContenido) === false) {
                $this->error("No se pudo guardar la imagen para el producto: {$producto->nombre}");
                return;
            }

            // Crear registro en la tabla productos_imagenes
            $imagen = new \App\Models\Inventario\Imagen();
            $imagen->id_producto = $producto->id;
            $imagen->img = '/productos/' . $nombreImagen;
            $imagen->shopify_image_id = $shopifyImageId;
            
            $this->info("Guardando nueva imagen en base de datos para producto {$producto->id}");
            
            $imagen->save();

            $this->info("Imagen guardada exitosamente para producto: {$producto->nombre}");
            $this->info("Ruta de imagen: /productos/{$nombreImagen}");

        } catch (\Exception $e) {
            $this->error("Error descargando imagen para producto {$producto->nombre}: " . $e->getMessage());
        }
    }

    /**
     * Obtener URL de imagen desde archivo (para comparación)
     */
    private function obtenerUrlImagenDesdeArchivo($rutaImagen)
    {
        // Esta es una implementación simplificada
        // En un caso real, podrías necesitar almacenar la URL original en la base de datos
        return null; // Por ahora retornamos null para forzar la descarga
    }

    /**
     * Descargar imagen desde URL usando cURL
     */
    private function descargarImagenDesdeUrl($url)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SmartPyme/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $contenido = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->error("Error cURL descargando imagen: {$error}");
                return false;
            }

            if ($httpCode !== 200) {
                $this->error("HTTP error descargando imagen: {$httpCode}");
                return false;
            }

            if (empty($contenido)) {
                $this->error("Imagen vacía descargada");
                return false;
            }

            return $contenido;

        } catch (\Exception $e) {
            $this->error("Excepción descargando imagen: " . $e->getMessage());
            return false;
        }
    }
}
