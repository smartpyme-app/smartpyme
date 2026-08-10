<?php

namespace App\Services\Restaurante;

use App\Jobs\Restaurante\ProcesarSideEffectRestauranteJob;
use App\Models\Restaurante\RestauranteSideEffect;
use Illuminate\Support\Facades\Log;

/**
 * Encola side-effects no críticos tras commit. Idempotente vía unique outbox (MariaDB).
 */
class RestauranteSideEffectDispatcher
{
    public function enqueueComandaTicket(int $comandaId, int $empresaId): void
    {
        $this->enqueue(
            RestauranteSideEffect::TYPE_COMANDA_TICKET,
            'comanda',
            $comandaId,
            $empresaId
        );
    }

    public function enqueuePreCuentaTicket(int $preCuentaId, int $empresaId): void
    {
        $this->enqueue(
            RestauranteSideEffect::TYPE_PRECUENTA_TICKET,
            'precuenta',
            $preCuentaId,
            $empresaId
        );
    }

    public function enqueue(string $type, string $resourceType, int $resourceId, int $empresaId, array $payload = []): void
    {
        if (! config('restaurante.side_effects_enabled', true)) {
            return;
        }

        try {
            $effect = RestauranteSideEffect::query()->firstOrCreate(
                [
                    'type' => $type,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                ],
                [
                    'id_empresa' => $empresaId,
                    'status' => RestauranteSideEffect::STATUS_PENDING,
                    'payload' => $payload,
                    'attempts' => 0,
                ]
            );

            if ($effect->status === RestauranteSideEffect::STATUS_DONE) {
                return;
            }

            if ($effect->wasRecentlyCreated === false && $effect->status === RestauranteSideEffect::STATUS_FAILED) {
                $effect->update([
                    'status' => RestauranteSideEffect::STATUS_PENDING,
                    'last_error' => null,
                ]);
            }

            ProcesarSideEffectRestauranteJob::dispatch($effect->id)
                ->onQueue((string) config('restaurante.side_effects_queue', 'default'))
                ->afterCommit();
        } catch (\Throwable $e) {
            // Never fail the business transaction because of side-effect plumbing.
            Log::warning('restaurante.side_effect.enqueue_failed', [
                'type' => $type,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
