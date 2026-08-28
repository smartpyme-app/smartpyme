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
     * Procesar imágenes de Shopify (valida URL y reemplaza solo si cambió URL/hash).
     */
    private function procesarImagenesShopify($parametros)
    {
        try {
            $productoId = $parametros['producto_id'] ?? null;
            $imagenes = $parametros['imagenes'] ?? [];

            if (!$productoId) {
                throw new \Exception("producto_id no proporcionado");
            }

            $producto = \App\Models\Inventario\Producto::find($productoId);
            $nombre = $producto ? $producto->nombre : 'N/A';

            $this->info("🖼️  Procesando imágenes para producto ID: {$productoId}");

            $service = app(\App\Services\ShopifyImageService::class);
            $stats = $service->sincronizarImagenes((int) $productoId, $imagenes);

            $resultado = [
                'success' => ($stats['nuevas'] + $stats['reemplazadas'] + $stats['sin_cambios']) > 0,
                'imagenes_procesadas' => $stats['nuevas'] + $stats['reemplazadas'],
                'sin_cambios' => $stats['sin_cambios'],
                'invalidas' => $stats['invalidas'],
                'errores' => $stats['errores'],
                'producto_id' => $productoId,
                'producto_nombre' => $nombre,
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
}