<?php

namespace App\Services\Restaurante;

/**
 * Canal de notificación no crítica (log hoy; webhooks/push en fases posteriores).
 * Deduplicación dura en outbox + Cache::add en el job.
 */
class RestauranteNotifier
{
    /** @var list<array<string, mixed>> */
    public static array $sentForTests = [];

    public function notify(string $event, array $context): void
    {
        if (app()->environment('testing')) {
            self::$sentForTests[] = ['event' => $event, 'context' => $context];
        }

        \Illuminate\Support\Facades\Log::info('restaurante.side_effect.'.$event, $context);
    }

    public static function resetTestSink(): void
    {
        self::$sentForTests = [];
    }
}
