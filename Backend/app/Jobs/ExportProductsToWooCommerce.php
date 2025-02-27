<?php

namespace App\Jobs;

use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Inventario\Producto;
use App\Services\WooCommerceExportService;
use Illuminate\Support\Facades\Log;

class ExportProductsToWooCommerce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Número de reintentos si falla el job
    public $timeout = 3600; // 1 hora
    public $maxExceptions = 3;

    protected $userId;
    protected $sucursalId;
    protected $chunkSize = 100; // Aumentamos el tamaño del chunk para mejor rendimiento
    protected $batchSize = 10; // Productos por lote en cada petición a WooCommerce

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $sucursalId)
    {
        $this->userId = $userId;
        $this->sucursalId = $sucursalId;
    }

    public function handle(WooCommerceExportService $exportService)
    {
        set_time_limit(3600); // Aumentamos el tiempo límite a 1 hora

        try {
            $user = User::findOrFail($this->userId);
            $user->woocommerce_sync_status = 'syncing';
            $user->woocommerce_error = null; // Limpiamos errores anteriores
            $user->save();

            $bodegas = Bodega::where('id_sucursal', $this->sucursalId)->pluck('id')->toArray();

            if (empty($bodegas)) {
                throw new \Exception("No se encontraron bodegas para la sucursal {$this->sucursalId}");
            }

            // Procesamos en chunks para mejor manejo de memoria
            Inventario::whereIn('id_bodega', $bodegas)
                ->where('stock', '>', 0)
                ->select('id_producto')
                ->distinct()
                ->orderBy('id_producto')
                ->chunk($this->chunkSize, function ($inventarioChunk) use ($exportService, $user, $bodegas) {
                    $productIds = $inventarioChunk->pluck('id_producto')->toArray();

                    // Procesamos los productos en lotes más pequeños
                    foreach (array_chunk($productIds, $this->batchSize) as $batch) {
                        $productos = Producto::whereIn('id', $batch)
                            ->where('enable', 1)
                            ->whereNotNull('codigo')
                            ->get();

                        if ($productos->isEmpty()) {
                            continue;
                        }

                        try {
                            $result = $exportService->exportarProductos($user, $productos, $bodegas);

                            Log::info("Lote procesado exitosamente", [
                                'productos_procesados' => $productos->count(),
                                'result' => $result
                            ]);

                            // Liberamos memoria
                            unset($productos);
                            gc_collect_cycles();
                        } catch (\Exception $e) {
                            Log::error("Error procesando lote de productos", [
                                'error' => $e->getMessage(),
                                'productos_ids' => $batch
                            ]);

                            // Continuamos con el siguiente lote en caso de error
                            continue;
                        }

                        // Pequeña pausa para no sobrecargar la API de WooCommerce
                        usleep(500000); // 0.5 segundos
                    }
                });

            $user->woocommerce_sync_status = 'completed';
            $user->woocommerce_last_sync = now();
            $user->save();

            Log::info("Exportación completada exitosamente", [
                'user_id' => $this->userId,
                'sucursal_id' => $this->sucursalId
            ]);
        } catch (\Exception $e) {
            Log::error("Error fatal en exportación de productos", [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($user)) {
                $user->woocommerce_sync_status = 'error';
                $user->woocommerce_error = "Error en exportación: " . $e->getMessage();
                $user->save();
            }

            throw $e; // Relanzamos la excepción para que el job pueda reintentarse
        }
    }
}
