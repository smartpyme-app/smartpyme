<?php

namespace App\Jobs\Restaurante;

use App\Models\Restaurante\RestauranteSideEffect;
use App\Services\Restaurante\RestauranteNotifier;
use App\Services\Restaurante\RestauranteTicketHtmlService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa outbox de impresión/notif. Seguro ante retries: SoT = fila done en MariaDB.
 */
class ProcesarSideEffectRestauranteJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 60];

    public int $uniqueFor = 120;

    public function __construct(public int $sideEffectId)
    {
    }

    public function uniqueId(): string
    {
        return 'restaurante-side-effect:'.$this->sideEffectId;
    }

    public function handle(RestauranteTicketHtmlService $tickets, RestauranteNotifier $notifier): void
    {
        $effect = RestauranteSideEffect::query()->find($this->sideEffectId);
        if (! $effect) {
            return;
        }

        if ($effect->status === RestauranteSideEffect::STATUS_DONE) {
            return;
        }

        DB::transaction(function () use ($effect) {
            $locked = RestauranteSideEffect::query()->whereKey($effect->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === RestauranteSideEffect::STATUS_DONE) {
                return;
            }
            $locked->update([
                'status' => RestauranteSideEffect::STATUS_PROCESSING,
                'attempts' => (int) $locked->attempts + 1,
            ]);
        });

        $effect->refresh();
        if ($effect->status === RestauranteSideEffect::STATUS_DONE) {
            return;
        }

        try {
            $this->process($effect, $tickets, $notifier);

            $effect->update([
                'status' => RestauranteSideEffect::STATUS_DONE,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            $effect->update([
                'status' => RestauranteSideEffect::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            throw $e;
        }
    }

    private function process(
        RestauranteSideEffect $effect,
        RestauranteTicketHtmlService $tickets,
        RestauranteNotifier $notifier
    ): void {
        $empresaId = (int) $effect->id_empresa;
        $resourceId = (int) $effect->resource_id;

        match ($effect->type) {
            RestauranteSideEffect::TYPE_COMANDA_TICKET => $this->processComanda($resourceId, $empresaId, $tickets, $notifier),
            RestauranteSideEffect::TYPE_PRECUENTA_TICKET => $this->processPreCuenta($resourceId, $empresaId, $tickets, $notifier),
            default => Log::warning('restaurante.side_effect.unknown_type', [
                'id' => $effect->id,
                'type' => $effect->type,
            ]),
        };
    }

    private function processComanda(
        int $comandaId,
        int $empresaId,
        RestauranteTicketHtmlService $tickets,
        RestauranteNotifier $notifier
    ): void {
        $tickets->rememberComandaHtml($comandaId, $empresaId);

        $dedupeKey = 'rest:notify:comanda:'.$comandaId;
        if (Cache::add($dedupeKey, 1, 86400)) {
            $notifier->notify('comanda_ticket_ready', [
                'id_empresa' => $empresaId,
                'comanda_id' => $comandaId,
            ]);
        }
    }

    private function processPreCuenta(
        int $preCuentaId,
        int $empresaId,
        RestauranteTicketHtmlService $tickets,
        RestauranteNotifier $notifier
    ): void {
        $tickets->rememberPreCuentaHtml($preCuentaId, $empresaId);

        $dedupeKey = 'rest:notify:precuenta:'.$preCuentaId;
        if (Cache::add($dedupeKey, 1, 86400)) {
            $notifier->notify('precuenta_ticket_ready', [
                'id_empresa' => $empresaId,
                'pre_cuenta_id' => $preCuentaId,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('restaurante.side_effect.job_failed', [
            'side_effect_id' => $this->sideEffectId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
