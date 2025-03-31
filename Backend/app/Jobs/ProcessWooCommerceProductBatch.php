<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\WooCommerceExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWooCommerceProductBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $sucursalId;
    protected $productIds;
    protected $bodegaId;
    protected $batchNumber;
    protected $totalBatches;

    public $timeout = 900; // 15 minutos por lote
    public $tries = 3;     // Intentar hasta 3 veces si falla

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
    }

    /**
     * Execute the job.
     */
    public function handle(WooCommerceExportService $exportService)
    {
        $user = User::find($this->userId);
        $empresa = Empresa::find($user->id_empresa);

        if (!$user) {
            Log::error("Usuario no encontrado", ['user_id' => $this->userId]);
            return;
        }

        try {
            Log::info("Procesando lote {$this->batchNumber} de {$this->totalBatches}", [
                'productos' => count($this->productIds)
            ]);

            // Obtener productos habilitados con código
            $productos = Producto::whereIn('id', $this->productIds)
                ->where('enable', 1)
                ->whereNotNull('codigo')
                ->get();

            if ($productos->isEmpty()) {
                Log::info("No hay productos válidos en el lote {$this->batchNumber}");
                $this->updateProgress($user, $empresa);
                return;
            }

            // Procesar en mini-lotes de 5 productos
            $miniBatchSize = 5;
            foreach ($productos->chunk($miniBatchSize) as $productosMiniBatch) {
                try {
                    // Convertir array a colección
                    $productosMiniBatch = collect($productosMiniBatch);

                    // Procesar mini-lote
                    $result = $exportService->exportarProductos($user, $productosMiniBatch, $this->bodegaId,$empresa);

                    Log::info("Mini-lote procesado", [
                        'lote' => $this->batchNumber,
                        'productos' => count($productosMiniBatch),
                        'resultado' => [
                            'creados' => $result['creados'],
                            'actualizados' => $result['actualizados'],
                            'errores' => $result['errores']
                        ]
                    ]);

                    // Pausa para no sobrecargar WooCommerce
                    sleep(2);
                } catch (\Exception $e) {
                    Log::error("Error procesando mini-lote", [
                        'lote' => $this->batchNumber,
                        'error' => $e->getMessage()
                    ]);

                    // Continuar con el siguiente mini-lote
                    continue;
                }
            }

            $this->updateProgress($user, $empresa);

            Log::info("Lote {$this->batchNumber} completado");
        } catch (\Exception $e) {
            Log::error("Error procesando lote {$this->batchNumber}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function updateProgress($user, $empresa)
    {
        $empresa->woocommerce_sync_processed_batches++;

        // Si este es el último lote, marcar como completado
        if ($empresa->woocommerce_sync_processed_batches >= $empresa->woocommerce_sync_total_batches) {
            $empresa->woocommerce_sync_status = 'completed';
            $empresa->woocommerce_last_sync = now();

            Log::info("Exportación a WooCommerce completada", [
                'empresa_id' => $empresa->id
            ]);
        }

        // Verificar que total_batches no sea cero antes de calcular el porcentaje
        if ($empresa->woocommerce_sync_total_batches > 0) {
            $empresa->woocommerce_sync_progress =
                intval(($empresa->woocommerce_sync_processed_batches / $empresa->woocommerce_sync_total_batches) * 100);
        } else {
            $empresa->woocommerce_sync_progress = 100; // Si no hay lotes, considerar como 100% completado
        }

        $empresa->save();
    }
}
