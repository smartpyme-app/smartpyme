<?php

namespace App\Jobs;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Services\ShopifyConsolidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConsolidarProductosShopifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public $timeout = 3600;

    public int $empresaId;
    public int $userId;
    public string $direccion;
    public array $opciones;

    public function __construct(int $empresaId, int $userId, string $direccion = 'shopify_to_sp', array $opciones = [])
    {
        $this->empresaId = $empresaId;
        $this->userId = $userId;
        $this->direccion = $direccion;
        $this->opciones = $opciones;
    }

    public function handle(ShopifyConsolidationService $service): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '0');

        $cacheKey = "shopify_consolidacion_{$this->empresaId}";

        $empresa = Empresa::find($this->empresaId);
        $user = User::find($this->userId);

        if (!$empresa || !$user) {
            Cache::put($cacheKey, [
                'estado' => 'error',
                'progreso' => 0,
                'mensaje' => 'Empresa o usuario no encontrado',
                'error' => 'No se encontraron las credenciales del usuario o empresa',
            ], 7200);
            return;
        }

        // Registrar inicio en caché
        Cache::put($cacheKey, [
            'estado' => 'procesando',
            'progreso' => 0,
            'mensaje' => 'Iniciando proceso de consolidación en segundo plano...',
            'total' => 0,
            'procesados' => 0,
            'vinculados' => 0,
            'actualizados' => 0,
            'creados' => 0,
            'errores' => 0,
            'direccion' => $this->direccion,
            'fecha_inicio' => now()->toIso8601String(),
        ], 7200);

        $onProgreso = function (int $porcentaje, string $mensaje, array $metricas) use ($cacheKey) {
            Cache::put($cacheKey, [
                'estado' => ($porcentaje >= 100) ? 'completado' : 'procesando',
                'progreso' => $porcentaje,
                'mensaje' => $mensaje,
                'total' => $metricas['total'] ?? 0,
                'procesados' => $metricas['procesados'] ?? 0,
                'vinculados' => $metricas['vinculados'] ?? 0,
                'actualizados' => $metricas['actualizados'] ?? 0,
                'creados' => $metricas['creados'] ?? 0,
                'errores' => $metricas['errores'] ?? 0,
                'direccion' => $this->direccion,
                'fecha_actualizacion' => now()->toIso8601String(),
            ], 7200);
        };

        try {
            if ($this->direccion === 'sp_to_shopify') {
                $metricas = $service->consolidarSmartpymeHaciaShopify($empresa, $user, $this->opciones, $onProgreso);
            } else {
                $metricas = $service->consolidarShopifyHaciaSmartpyme($empresa, $user, $this->opciones, $onProgreso);
            }

            Cache::put($cacheKey, [
                'estado' => 'completado',
                'progreso' => 100,
                'mensaje' => 'Consolidación finalizada exitosamente.',
                'total' => $metricas['total'] ?? 0,
                'procesados' => $metricas['procesados'] ?? 0,
                'vinculados' => $metricas['vinculados'] ?? 0,
                'actualizados' => $metricas['actualizados'] ?? 0,
                'creados' => $metricas['creados'] ?? 0,
                'errores' => $metricas['errores'] ?? 0,
                'direccion' => $this->direccion,
                'fecha_finalizacion' => now()->toIso8601String(),
            ], 7200);

            // Actualizar última sincronización en la empresa
            try {
                $empresa->shopify_last_sync = now();
                $empresa->save();
            } catch (\Throwable $t) {
                Log::warning("No se pudo actualizar shopify_last_sync en empresa #{$empresa->id}: " . $t->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error("ConsolidarProductosShopifyJob falló para empresa #{$this->empresaId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put($cacheKey, [
                'estado' => 'error',
                'progreso' => 0,
                'mensaje' => 'Ocurrió un error inesperado durante la consolidación.',
                'error' => $e->getMessage(),
                'direccion' => $this->direccion,
                'fecha_actualizacion' => now()->toIso8601String(),
            ], 7200);
        }
    }
}
