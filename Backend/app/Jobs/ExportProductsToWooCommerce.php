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

    protected $userId;
    protected $sucursalId;
    public $timeout = 3600; // 1 hora máximo de ejecución


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
        set_time_limit(300);
        try {
            $user = User::findOrFail($this->userId);

            $user->woocommerce_sync_status = 'syncing';
            $user->save();

            $bodegas = Bodega::where('id_sucursal', $this->sucursalId)->pluck('id')->toArray();

            if (empty($bodegas)) {
                throw new \Exception("No se encontraron bodegas para la sucursal {$this->sucursalId}");
            }

            Log::info("Bodegas encontradas para la sucursal", [
                'sucursal_id' => $this->sucursalId,
                'bodegas' => $bodegas
            ]);

            $productosConInventario = Inventario::whereIn('id_bodega', $bodegas)
                ->where('stock', '>', 0)
                ->pluck('id_producto')
                ->toArray();

            $totalProductos = count($productosConInventario);
            $batchSize = 5; // Procesar solo 5 productos a la vez



            Log::info("Total productos a procesar: {$totalProductos}");

            for ($i = 0; $i < $totalProductos; $i += $batchSize) {
                $idsBatch = array_slice($productosConInventario, $i, $batchSize);

                $productos = Producto::whereIn('id', $idsBatch)
                    ->where('enable', 1)
                    ->whereNotNull('codigo')
                    ->get();

                Log::info("Procesando lote " . ($i / $batchSize + 1) . " de " . ceil($totalProductos / $batchSize), [
                    'productos_en_lote' => $productos->count()
                ]);

                if ($productos->count() > 0) {
                    $result = $exportService->exportarProductos($user, $productos, $bodegas);
                    Log::info("Lote procesado", $result);
                }
            }

            $user->woocommerce_sync_status = 'completed';
            $user->woocommerce_last_sync = now();
            $user->save();

            Log::info("Exportación completada");
        } catch (\Exception $e) {
            Log::error("Error en exportación de productos: " . $e->getMessage(), [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (isset($user)) {
                $user->woocommerce_sync_status = 'error';
                $user->woocommerce_error = "Error en exportación: " . $e->getMessage();
                $user->save();
            }
        }
    }
}
