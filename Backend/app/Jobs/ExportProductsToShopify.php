<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ExportProductsToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $sucursalId;
    protected $limit = 50; // Cantidad de productos por job trabajador
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $sucursalId)
    {
        $this->userId = $userId;
        $this->sucursalId = $sucursalId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        set_time_limit(3600);
        try {
            // Actualizar estado del usuario
            $user = User::findOrFail($this->userId);
            $empresa = Empresa::find($user->id_empresa);
            $empresa->shopify_sync_status = 'syncing';
            $empresa->shopify_error = null;
            $empresa->save();

            Log::info("Iniciando exportación a Shopify", [
                'user_id' => $this->userId,
                'sucursal_id' => $this->sucursalId
            ]);

            // Obtener bodegas
            $bodega = Bodega::where('id', $this->sucursalId)
                ->first();

            if (!$bodega) {
                throw new \Exception("No se encontraron bodegas para la sucursal {$this->sucursalId}");
            }

            // Obtener IDs de productos con inventario
            $productosIds = Inventario::where('id_bodega', $bodega->id)
                ->where('stock', '>', 0)
                ->select('id_producto')
                ->distinct()
                ->pluck('id_producto')
                ->toArray();

            $totalProductos = count($productosIds);

            Log::info("Total de productos a procesar en Shopify: {$totalProductos}");

            if ($totalProductos == 0) {
                $empresa->shopify_sync_status = 'completed';
                $empresa->shopify_last_sync = now();
                $empresa->save();

                Log::info("No hay productos para sincronizar con Shopify");
                return;
            }

            // Dividir en lotes (Shopify tiene rate limits más estrictos)
            $lotes = array_chunk($productosIds, $this->limit);
            $totalLotes = count($lotes);

            Log::info("Dividiendo exportación a Shopify en {$totalLotes} lotes");

            // Crear un job para cada lote de productos
            foreach ($lotes as $index => $loteIds) {
                // Retrasar cada job más tiempo para Shopify (60 segundos entre lotes)
                $delay = now()->addSeconds($index * 60);

                // Encolar job trabajador
                ProcessShopifyProductBatch::dispatch(
                    $this->userId,
                    $this->sucursalId,
                    $loteIds,
                    $bodega->id,
                    $index + 1,
                    $totalLotes
                )->delay($delay);
            }

            // Registrar que los jobs han sido encolados
            $empresa->shopify_sync_progress = 0; // Progreso inicial
            $empresa->shopify_sync_total_batches = $totalLotes;
            $empresa->shopify_sync_processed_batches = 0;
            $empresa->save();

            Log::info("Todos los lotes de Shopify han sido programados");
        } catch (\Exception $e) {
            if (isset($empresa)) {
                $empresa->shopify_sync_status = 'error';
                $empresa->shopify_error = "Error en exportación: " . $e->getMessage();
                $empresa->save();
            }

            Log::error("Error iniciando exportación a Shopify", [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}