<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ShopifyExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyProductBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    protected $userId;
    protected $sucursalId;
    protected $productIds;
    protected $bodegaId;
    protected $batchNumber;
    protected $totalBatches;

    public $timeout = 1200; // 20 minutos por lote (Shopify es más lento)
    public $tries = 3;      // Intentar hasta 3 veces si falla

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $sucursalId, $productIds, $bodegaId, $batchNumber, $totalBatches)
    {
        $this->userId = $userId;
        $this->sucursalId = $sucursalId;
        $this->productIds = $productIds;
        $this->bodegaId = $bodegaId;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;
        $this->onQueue('smartpyme-shopify-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(ShopifyExportService $exportService)
    {
        $user = User::find($this->userId);
        $empresa = Empresa::find($user->id_empresa);

        if (!$user) {
            Log::error("Usuario no encontrado para Shopify", ['user_id' => $this->userId]);
            return;
        }

        try {
            Log::info("Procesando lote Shopify {$this->batchNumber} de {$this->totalBatches}", [
                'productos' => count($this->productIds)
            ]);

            // Obtener productos habilitados con código
            $productos = Producto::whereIn('id', $this->productIds)
                ->where('enable', 1)
                ->whereNotNull('codigo')
                ->get();

            if ($productos->isEmpty()) {
                Log::info("No hay productos válidos en el lote Shopify {$this->batchNumber}");
                $this->updateProgress($user, $empresa);
                return;
            }

            // Procesar en mini-lotes más pequeños para Shopify (3 productos por vez)
            $miniBatchSize = 3;
            foreach ($productos->chunk($miniBatchSize) as $productosMiniBatch) {
                try {
                    // Convertir array a colección
                    $productosMiniBatch = collect($productosMiniBatch);

                    // Procesar mini-lote
                    $result = $exportService->exportarProductos($user, $productosMiniBatch, $this->bodegaId);

                    Log::info("Mini-lote Shopify procesado", [
                        'lote' => $this->batchNumber,
                        'productos' => count($productosMiniBatch),
                        'resultado' => [
                            'creados' => $result['creados'],
                            'actualizados' => $result['actualizados'],
                            'errores' => $result['errores']
                        ]
                    ]);

                    // Pausa más larga para Shopify (rate limit de 40 req/seg)
                    sleep(5);
                } catch (\Exception $e) {
                    Log::error("Error procesando mini-lote Shopify", [
                        'lote' => $this->batchNumber,
                        'error' => $e->getMessage()
                    ]);

                    // Si es error de rate limit, esperar más tiempo
                    if (str_contains($e->getMessage(), 'rate') || str_contains($e->getMessage(), '429')) {
                        Log::warning("Rate limit detectado, esperando 30 segundos");
                        sleep(30);
                    }

                    // Continuar con el siguiente mini-lote
                    continue;
                }
            }

            $this->updateProgress($user, $empresa);

            Log::info("Lote Shopify {$this->batchNumber} completado");
        } catch (\Exception $e) {
            Log::error("Error procesando lote Shopify {$this->batchNumber}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function updateProgress($user, $empresa)
    {
        $empresa->shopify_sync_processed_batches++;

        // Si este es el último lote, marcar como completado
        if ($empresa->shopify_sync_processed_batches >= $empresa->shopify_sync_total_batches) {
            $empresa->shopify_sync_status = 'completed';
            $empresa->shopify_last_sync = now();

            Log::info("Exportación a Shopify completada", [
                'empresa_id' => $empresa->id
            ]);
        }

        // Verificar que total_batches no sea cero antes de calcular el porcentaje
        if ($empresa->shopify_sync_total_batches > 0) {
            $empresa->shopify_sync_progress =
                intval(($empresa->shopify_sync_processed_batches / $empresa->shopify_sync_total_batches) * 100);
        } else {
            $empresa->shopify_sync_progress = 100; // Si no hay lotes, considerar como 100% completado
        }

        $empresa->save();
    }
}