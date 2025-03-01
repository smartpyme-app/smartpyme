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
use Illuminate\Support\Facades\Log;

class ExportProductsToWooCommerce implements ShouldQueue
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
            $user->woocommerce_sync_status = 'syncing';
            $user->woocommerce_error = null;
            $user->save();

            Log::info("Iniciando exportación a WooCommerce", [
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

            Log::info("Total de productos a procesar: {$totalProductos}");

            if ($totalProductos == 0) {
                $user->woocommerce_sync_status = 'completed';
                $user->woocommerce_last_sync = now();
                $user->save();

                Log::info("No hay productos para sincronizar");
                return;
            }

            // Dividir en lotes
            $lotes = array_chunk($productosIds, $this->limit);
            $totalLotes = count($lotes);

            Log::info("Dividiendo exportación en {$totalLotes} lotes");

            // Crear un job para cada lote de productos
            foreach ($lotes as $index => $loteIds) {
                // Retrasar cada job para que no se ejecuten todos a la vez
                // Inicio escalonado: el primer lote inicia inmediatamente, los siguientes cada 30 segundos
                $delay = now()->addSeconds($index * 30);

                // Encolar job trabajador
                ProcessWooCommerceProductBatch::dispatch(
                    $this->userId,
                    $this->sucursalId,
                    $loteIds,
                    $bodega->id,
                    $index + 1,
                    $totalLotes
                )->delay($delay);
            }

            // Registrar que los jobs han sido encolados
            $user->woocommerce_sync_progress = 0; // Progreso inicial
            $user->woocommerce_sync_total_batches = $totalLotes;
            $user->woocommerce_sync_processed_batches = 0;
            $user->save();

            Log::info("Todos los lotes han sido programados");
        } catch (\Exception $e) {
            if (isset($user)) {
                $user->woocommerce_sync_status = 'error';
                $user->woocommerce_error = "Error en exportación: " . $e->getMessage();
                $user->save();
            }

            Log::error("Error iniciando exportación a WooCommerce", [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
