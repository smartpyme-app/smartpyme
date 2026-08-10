<?php

namespace App\Services\Restaurante;

use App\Events\Restaurante\CocinaComandasChanged;
use App\Events\Restaurante\MapaMesasChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emite hints realtime. Fallos de broadcast NUNCA afectan la operación de negocio.
 * MariaDB sigue siendo SoT; el cliente refresca vía GET.
 */
class RestauranteRealtimePublisher
{
    public function mapaChanged(
        int $idEmpresa,
        ?int $mesaId = null,
        ?string $estado = null,
        ?int $sesionId = null,
        string $reason = 'mapa'
    ): void {
        if (! config('restaurante.realtime_enabled', true)) {
            return;
        }

        try {
            event(new MapaMesasChanged($idEmpresa, $mesaId, $estado, $sesionId, $reason));
        } catch (Throwable $e) {
            Log::warning('restaurante.realtime.mapa_publish_failed', [
                'id_empresa' => $idEmpresa,
                'mesa_id' => $mesaId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cocinaChanged(
        int $idEmpresa,
        ?int $comandaId = null,
        ?string $destino = null,
        ?string $estado = null,
        string $reason = 'cocina'
    ): void {
        if (! config('restaurante.realtime_enabled', true)) {
            return;
        }

        try {
            event(new CocinaComandasChanged($idEmpresa, $comandaId, $destino, $estado, $reason));
        } catch (Throwable $e) {
            Log::warning('restaurante.realtime.cocina_publish_failed', [
                'id_empresa' => $idEmpresa,
                'comanda_id' => $comandaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
