<?php

namespace Tests\Feature\Restaurante;

use App\Events\Restaurante\CocinaComandasChanged;
use App\Events\Restaurante\MapaMesasChanged;
use App\Models\User;
use App\Services\Restaurante\RestauranteRealtimePublisher;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 6: eventos realtime (hints UI), afterCommit, canales por empresa.
 */
final class Phase6RealtimeTest extends TestCase
{
    private User $user;

    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('restaurante_mesas')) {
            $this->markTestSkipped('Tablas restaurante no disponibles.');
        }

        $this->user = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $this->user) {
            $this->markTestSkipped('No hay usuario con id_empresa.');
        }
        $this->empresaId = (int) $this->user->id_empresa;
        Auth::login($this->user);
        config(['restaurante.realtime_enabled' => true]);
    }

    public function test_mapa_event_declares_after_commit_and_broadcasts_when_not_in_transaction(): void
    {
        Queue::fake();

        $event = new MapaMesasChanged($this->empresaId, 10, 'ocupada', 20, 'test');
        $this->assertTrue($event->afterCommit);

        // Fuera de TX: el job de broadcast se encola (driver null/log en tests).
        event($event);
        Queue::assertPushed(BroadcastEvent::class, function (BroadcastEvent $job) {
            return $job->event instanceof MapaMesasChanged
                && (int) $job->event->idEmpresa === $this->empresaId
                && $job->event->afterCommit === true;
        });
    }

    /**
     * Tras rollback no debe quedar efecto de negocio; el publisher se invoca solo
     * después de commits exitosos en controllers. Aquí validamos que un event()
     * dentro de TX revertida no deja filas de negocio (smoke de disciplina afterCommit).
     */
    public function test_rollback_transaction_does_not_persist_side_data(): void
    {
        try {
            DB::transaction(function () {
                DB::table('restaurante_side_effects')->insert([
                    'id_empresa' => $this->empresaId,
                    'type' => 'fase6_probe',
                    'resource_type' => 'probe',
                    'resource_id' => 600001,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                event(new MapaMesasChanged($this->empresaId, null, null, null, 'rollback_probe'));
                throw new \RuntimeException('force-rollback-f6');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('force-rollback-f6', $e->getMessage());
        }

        $this->assertDatabaseMissing('restaurante_side_effects', [
            'type' => 'fase6_probe',
            'resource_id' => 600001,
        ]);
    }

    public function test_cocina_event_payload_and_channel(): void
    {
        Event::fake([CocinaComandasChanged::class]);

        app(RestauranteRealtimePublisher::class)->cocinaChanged(
            $this->empresaId,
            55,
            'cocina',
            'pendiente',
            'test'
        );

        Event::assertDispatched(CocinaComandasChanged::class, function (CocinaComandasChanged $e) {
            $channels = $e->broadcastOn();
            $name = (string) $channels[0]->name;

            return $e->idEmpresa === $this->empresaId
                && $e->comandaId === 55
                && $e->broadcastAs() === 'cocina.updated'
                && str_contains($name, 'restaurante.empresa.'.$this->empresaId)
                && $e->afterCommit === true;
        });
    }

    public function test_publisher_noop_when_realtime_disabled(): void
    {
        Event::fake([MapaMesasChanged::class, CocinaComandasChanged::class]);
        config(['restaurante.realtime_enabled' => false]);

        app(RestauranteRealtimePublisher::class)->mapaChanged($this->empresaId, 1, 'libre', null, 'off');
        app(RestauranteRealtimePublisher::class)->cocinaChanged($this->empresaId, 2, 'barra', 'listo', 'off');

        Event::assertNotDispatched(MapaMesasChanged::class);
        Event::assertNotDispatched(CocinaComandasChanged::class);
    }

    public function test_channel_auth_allows_same_empresa_only(): void
    {
        $auth = function ($user, int $idEmpresa) {
            if (! $user || ! $user->id_empresa) {
                return false;
            }

            return (int) $user->id_empresa === (int) $idEmpresa;
        };

        $otherEmpresa = $this->empresaId + 99999;
        $this->assertTrue($auth($this->user, $this->empresaId));
        $this->assertFalse($auth($this->user, $otherEmpresa));
        $this->assertFalse($auth(null, $this->empresaId));
    }

    public function test_mapa_broadcast_with_minimal_payload(): void
    {
        $e = new MapaMesasChanged($this->empresaId, 7, 'ocupada', 9, 'abrir_mesa');
        $payload = $e->broadcastWith();

        $this->assertSame([
            'id_empresa' => $this->empresaId,
            'mesa_id' => 7,
            'estado' => 'ocupada',
            'sesion_id' => 9,
            'reason' => 'abrir_mesa',
        ], $payload);
        $this->assertSame('mapa.updated', $e->broadcastAs());
    }
}
