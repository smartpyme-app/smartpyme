<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrabajosPendientes;
use App\Services\MHPruebasMasivasService;
use Illuminate\Support\Facades\Log;

class ProcesarTrabajosPendientes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trabajos:procesar {--limite=5} {--duracion=58} {--id=} {--solo-imagenes-shopify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los trabajos pendientes en la base de datos. Usa --solo-imagenes-shopify para procesar solo jobs de imágenes de Shopify';

    /**
     * Hora de inicio del script
     */
    protected $horaInicio;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->horaInicio = time();
    }

    public function handle()
    {
        $limite = $this->option('limite');
        $id = $this->option('id');
        $soloImagenesShopify = $this->option('solo-imagenes-shopify');

        $query = TrabajosPendientes::where('estado', 'pendiente');

        // Filtrar por tipo según la opción seleccionada
        if ($soloImagenesShopify) {
            $query->where('tipo', 'procesar_imagenes_shopify');
            $this->info('🔍 Modo: Solo procesar imágenes de Shopify');
        } else {
            $query->whereIn('tipo', ['pruebas_masivas', 'procesar_imagenes_shopify']);
            $this->info('🔍 Modo: Procesar todos los tipos de trabajos');
        }

        if ($id) {
            $query->where('id', $id);
        }

        $trabajos = $query->orderBy('fecha_creacion', 'asc')
            ->limit($limite)
            ->get();

        if ($trabajos->isEmpty()) {
            $tipoFiltro = $soloImagenesShopify ? 'imágenes de Shopify' : 'trabajos';
            $this->info("No hay {$tipoFiltro} pendientes para procesar.");
            return;
        }

        // Mostrar resumen de trabajos por tipo
        $resumenTipos = $trabajos->groupBy('tipo');
        $this->info("📊 Resumen de trabajos a procesar:");
        foreach ($resumenTipos as $tipo => $trabajosTipo) {
            $this->info("   • {$tipo}: {$trabajosTipo->count()} trabajo(s)");
        }

        $this->info("🚀 Procesando {$trabajos->count()} trabajo(s) pendiente(s)...");

        foreach ($trabajos as $trabajo) {
            $this->procesarTrabajo($trabajo);
        }

        $this->info('Procesamiento completado.');
    }

    private function procesarTrabajo(TrabajosPendientes $trabajo)
    {
        try {
            // Marcar como en proceso
            $trabajo->update([
                'estado' => 'procesando',
                'fecha_inicio' => now()
            ]);

            $this->info("Procesando trabajo ID: {$trabajo->id}");

            // Decodificar parámetros
            $parametros = json_decode($trabajo->parametros, true);

            // Procesar según el tipo de trabajo
            if ($trabajo->tipo === 'pruebas_masivas') {
                // Crear instancia del servicio
                $service = new MHPruebasMasivasService();

                // EJECUTAR EL PROCESO ACTUALIZADO
                $resultado = $service->procesarPruebasMasivas(
                    $parametros['tipo_dte'],
                    $parametros['cantidad'],
                    $parametros['id_documento_base'] ?? null,
                    $parametros['id_usuario'],
                    $parametros['correlativo_inicial'] ?? null
                );
            } elseif ($trabajo->tipo === 'procesar_imagenes_shopify') {
                // Procesar imágenes de Shopify
                $resultado = $this->procesarImagenesShopify($parametros);
            } else {
                throw new \Exception("Tipo de trabajo no soportado: {$trabajo->tipo}");
            }

            // Actualizar el trabajo según el resultado
            if ($resultado['success']) {
                $trabajo->update([
                    'estado' => 'completado',
                    'resultado' => json_encode($resultado),
                    'fecha_fin' => now()
                ]);

                // Mensaje específico según el tipo de trabajo
                if ($trabajo->tipo === 'procesar_imagenes_shopify') {
                    $imagenesProcesadas = $resultado['imagenes_procesadas'] ?? 0;
                    $this->info("✓ Trabajo {$trabajo->id} completado - {$imagenesProcesadas} imagen(es) procesada(s)");
                } else {
                    $this->info("✓ Trabajo {$trabajo->id} completado exitosamente");
                    
                    // Log adicional para CCF con notas automáticas (solo para pruebas_masivas)
                    if (isset($parametros['tipo_dte']) && $parametros['tipo_dte'] === '03') {
                        $this->info("  → CCF generados con notas automáticas incluidas");
                    }
                }
            } else {
                $trabajo->update([
                    'estado' => 'fallido',
                    'resultado' => json_encode($resultado),
                    'fecha_fin' => now()
                ]);

                $mensajeError = $resultado['message'] ?? $resultado['error'] ?? 'Error desconocido';
                $this->error("✗ Trabajo {$trabajo->id} falló: " . $mensajeError);
            }

        } catch (\Exception $e) {
            // Marcar como fallido en caso de excepción
            $trabajo->update([
                'estado' => 'fallido',
                'resultado' => json_encode(['error' => $e->getMessage()]),
                'fecha_fin' => now()
            ]);

            $this->error("✗ Error en trabajo {$trabajo->id}: " . $e->getMessage());
            Log::error("Error procesando trabajo {$trabajo->id}: " . $e->getMessage());
        }
    }

    /**
     * Procesar imágenes de Shopify
     */
    private function procesarImagenesShopify($parametros)
    {
        try {
            $productoId = $parametros['producto_id'];
            $imagenes = $parametros['imagenes'];
            $totalImagenes = count($imagenes);

            $this->info("🖼️  Procesando imágenes para producto ID: {$productoId}");
            $this->info("📸 Total imágenes disponibles: {$totalImagenes} (solo se procesará la primera)");

            // Obtener el producto
            $producto = \App\Models\Inventario\Producto::find($productoId);
            if (!$producto) {
                throw new \Exception("Producto no encontrado: {$productoId}");
            }

            // Eliminar imágenes existentes del producto
            $this->eliminarImagenesExistentes($productoId);

            $imagenesProcesadas = 0;
            $errores = [];

            // Procesar solo la primera imagen (optimización)
            if (!empty($imagenes)) {
                $primeraImagen = $imagenes[0];
                $resultado = $this->descargarYGuardarImagen($producto, $primeraImagen, 0);
                
                if ($resultado) {
                    $imagenesProcesadas = 1;
                    $this->info("✅ Imagen procesada exitosamente para producto: {$producto->nombre}");
                } else {
                    $errores[] = "Error descargando imagen principal";
                    $this->error("❌ Error procesando imagen para producto: {$producto->nombre}");
                }
            }

            $resultado = [
                'success' => $imagenesProcesadas > 0,
                'imagenes_procesadas' => $imagenesProcesadas,
                'total_imagenes_disponibles' => $totalImagenes,
                'producto_id' => $productoId,
                'producto_nombre' => $producto->nombre,
                'errores' => $errores,
                'optimizacion_aplicada' => true
            ];

            Log::info("Procesamiento de imágenes Shopify completado", $resultado);
            return $resultado;

        } catch (\Exception $e) {
            Log::error("Error procesando imágenes Shopify: " . $e->getMessage(), [
                'parametros' => $parametros,
                'error_trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'producto_id' => $parametros['producto_id'] ?? 'N/A'
            ];
        }
    }

    /**
     * Eliminar imágenes existentes del producto
     */
    private function eliminarImagenesExistentes($productoId)
    {
        try {
            $imagenesExistentes = \App\Models\Inventario\Imagen::where('id_producto', $productoId)->get();
            
            foreach ($imagenesExistentes as $imagen) {
                // Eliminar archivo físico si existe
                $rutaImagen = public_path('img' . $imagen->img);
                if (file_exists($rutaImagen)) {
                    unlink($rutaImagen);
                }
                
                // Eliminar registro de la base de datos
                $imagen->delete();
            }

            if ($imagenesExistentes->count() > 0) {
                $this->info("Imágenes existentes eliminadas: {$imagenesExistentes->count()}");
            }

        } catch (\Exception $e) {
            Log::error("Error eliminando imágenes existentes: " . $e->getMessage(), [
                'producto_id' => $productoId,
                'error_trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Descargar y guardar imagen desde Shopify
     */
    private function descargarYGuardarImagen($producto, $imagenShopify, $index)
    {
        try {
            $urlImagen = $imagenShopify['src'] ?? null;
            if (!$urlImagen) {
                $this->warn("Imagen sin URL válida para producto: {$producto->nombre}");
                return false;
            }

            // Crear directorio si no existe
            $directorioProductos = public_path('img/productos');
            if (!file_exists($directorioProductos)) {
                mkdir($directorioProductos, 0755, true);
            }

            // Generar nombre único para la imagen
            $extension = pathinfo(parse_url($urlImagen, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'jpg';
            
            $nombreArchivo = 'producto_' . $producto->id . '_' . $index . '_' . time() . '.' . $extension;
            $rutaCompleta = $directorioProductos . '/' . $nombreArchivo;

            // Descargar imagen
            $imagenContenido = $this->descargarImagenDesdeUrl($urlImagen);
            if (!$imagenContenido) {
                $this->warn("No se pudo descargar la imagen para producto: {$producto->nombre}");
                return false;
            }

            // Guardar archivo
            if (file_put_contents($rutaCompleta, $imagenContenido) === false) {
                $this->error("Error guardando archivo de imagen para producto: {$producto->nombre}");
                return false;
            }

            // Guardar en base de datos
            $imagen = new \App\Models\Inventario\Imagen();
            $imagen->id_producto = $producto->id;
            $imagen->img = '/productos/' . $nombreArchivo;
            $imagen->shopify_image_id = $imagenShopify['id'] ?? null;
            $imagen->save();

            $this->info("💾 Imagen descargada y guardada: {$nombreArchivo}");
            return true;

        } catch (\Exception $e) {
            Log::error("Error descargando y guardando imagen: " . $e->getMessage(), [
                'producto_id' => $producto->id,
                'url_imagen' => $urlImagen ?? 'N/A',
                'error_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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
                Log::error("Error cURL descargando imagen", [
                    'url' => $url,
                    'error' => $error
                ]);
                return false;
            }

            if ($httpCode !== 200) {
                Log::warning("HTTP error descargando imagen", [
                    'url' => $url,
                    'http_code' => $httpCode
                ]);
                return false;
            }

            if (empty($contenido)) {
                Log::warning("Imagen vacía descargada", [
                    'url' => $url
                ]);
                return false;
            }

            return $contenido;

        } catch (\Exception $e) {
            Log::error("Excepción descargando imagen: " . $e->getMessage(), [
                'url' => $url,
                'error_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}