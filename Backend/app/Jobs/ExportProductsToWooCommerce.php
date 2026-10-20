<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Services\WooCommerceSpExportCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExportProductsToWooCommerce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $sucursalId;
    /** @var bool */
    protected $soloConStock;
    protected $limit = 12; // Grupos SKU por lote
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $sucursalId, bool $soloConStock = true)
    {
        $this->userId = $userId;
        $this->sucursalId = $sucursalId;
        $this->soloConStock = $soloConStock;
    }

    /**
     * Execute the job.
     */
    public function handle(WooCommerceSpExportCatalogService $catalog)
    {
        set_time_limit(3600);
        try {
            $user = User::findOrFail($this->userId);
            $empresa = Empresa::find($user->id_empresa);

            if ($empresa && !$empresa->woocommerceSyncPushesToRemote()) {
                Log::info('Exportación a WooCommerce omitida: modo actual no envía datos a WooCommerce', [
                    'empresa_id' => $empresa->id,
                    'woocommerce_sync_mode' => $empresa->woocommerce_sync_mode,
                ]);
                return;
            }

            if (!$empresa) {
                throw new \Exception('Empresa no encontrada');
            }

            $empresa->woocommerce_sync_status = 'syncing';
            $empresa->woocommerce_error = null;
            $empresa->woocommerce_sync_processed_batches = 0;
            $empresa->save();

            Cache::put('woocommerce_export_' . $empresa->id, [
                'estado' => 'procesando',
                'progreso' => 0,
                'mensaje' => 'Iniciando exportación...',
                'lotes_total' => 0,
                'lotes_procesados' => 0,
                'creados' => 0,
                'actualizados' => 0,
                'errores' => 0,
                'fecha_inicio' => now()->toIso8601String(),
            ], 7200);

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

            $productos = $catalog->productosElegibles($user, $bodega->id, $this->soloConStock);
            $groups = $catalog->groupForExport($productos);

            Log::info('Exportación WooCommerce: productos y grupos', [
                'productos' => $productos->count(),
                'grupos_sku' => count($groups),
                'solo_con_stock' => $this->soloConStock,
            ]);

            if (count($groups) === 0) {
                $empresa->woocommerce_sync_status = 'completed';
                $empresa->woocommerce_last_sync = now();
                $empresa->save();

                Log::info('No hay productos para sincronizar');
                return;
            }

            $lotes = array_chunk($groups, $this->limit);
            $totalLotes = count($lotes);

            foreach ($lotes as $index => $groupChunk) {
                $loteIds = [];
                foreach ($groupChunk as $group) {
                    foreach ($group['items'] as $producto) {
                        $loteIds[] = $producto->id;
                    }
                }
                $delay = now()->addSeconds($index * 30);

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
            $empresa->woocommerce_sync_progress = 0; // Progreso inicial
            $empresa->woocommerce_sync_total_batches = $totalLotes;
            $empresa->woocommerce_sync_processed_batches = 0;
            $empresa->save();

            Log::info("Todos los lotes han sido programados");
        } catch (\Exception $e) {
            if (isset($empresa)) {
                $empresa->woocommerce_sync_status = 'error';
                $empresa->woocommerce_error = "Error en exportación: " . $e->getMessage();
                $empresa->save();
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
