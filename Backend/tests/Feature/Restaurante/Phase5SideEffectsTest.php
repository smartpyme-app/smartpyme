<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Jobs\Restaurante\ProcesarSideEffectRestauranteJob;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\RestauranteSideEffect;
use App\Models\Restaurante\SesionMesa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\Restaurante\RestauranteNotifier;
use App\Services\Restaurante\RestauranteSideEffectDispatcher;
use App\Services\Restaurante\RestauranteTicketHtmlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 5: outbox + jobs de impresión/notif post-commit, retries e idempotencia.
 */
final class Phase5SideEffectsTest extends TestCase
{
    private User $user;

    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('restaurante_mesas') || ! Schema::hasTable('restaurante_side_effects')) {
            $this->markTestSkipped('Tablas restaurante / side_effects no disponibles.');
        }

        $this->user = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $this->user) {
            $this->markTestSkipped('No hay usuario con id_empresa.');
        }
        $this->empresaId = (int) $this->user->id_empresa;
        Auth::login($this->user);
        RestauranteNotifier::resetTestSink();
        config(['restaurante.side_effects_enabled' => true]);
    }

    public function test_enviar_comanda_dispatches_side_effect_job_after_commit(): void
    {
        Queue::fake();

        $sesion = $this->crearSesionConItemPendiente('F5-DISP');

        $request = Request::create("/api/restaurante/sesiones-mesa/{$sesion->id}/comandas", 'POST');
        $request->headers->set('Idempotency-Key', 'f5-disp-'.uniqid('', true));
        $response = app(ComandaController::class)->store($request, (int) $sesion->id);
        $this->assertSame(201, $response->getStatusCode());

        Queue::assertPushed(ProcesarSideEffectRestauranteJob::class);
        $this->assertDatabaseHas('restaurante_side_effects', [
            'type' => RestauranteSideEffect::TYPE_COMANDA_TICKET,
            'resource_type' => 'comanda',
            'id_empresa' => $this->empresaId,
            'status' => RestauranteSideEffect::STATUS_PENDING,
        ]);

        $this->cleanupSesion($sesion);
    }

    public function test_side_effect_job_is_idempotent_on_retry(): void
    {
        Queue::fake();

        $sesion = $this->crearSesionConItemPendiente('F5-IDEM');
        $comanda = Comanda::create([
            'id_empresa' => $this->empresaId,
            'sesion_id' => $sesion->id,
            'numero_comanda' => 'F5-IDEM-1',
            'estado' => 'pendiente',
            'destino' => 'cocina',
            'enviado_at' => now(),
        ]);

        $effect = RestauranteSideEffect::create([
            'id_empresa' => $this->empresaId,
            'type' => RestauranteSideEffect::TYPE_COMANDA_TICKET,
            'resource_type' => 'comanda',
            'resource_id' => $comanda->id,
            'status' => RestauranteSideEffect::STATUS_PENDING,
            'attempts' => 0,
        ]);

        Cache::forget('rest:notify:comanda:'.$comanda->id);
        Cache::forget(app(RestauranteTicketHtmlService::class)->cacheKeyComanda((int) $comanda->id));

        $notifier = $this->createMock(RestauranteNotifier::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with(
                'comanda_ticket_ready',
                $this->callback(fn ($ctx) => (int) ($ctx['comanda_id'] ?? 0) === (int) $comanda->id)
            );

        $job = new ProcesarSideEffectRestauranteJob((int) $effect->id);
        $job->handle(app(RestauranteTicketHtmlService::class), $notifier);
        // Segunda ejecución: outbox already done → no re-notifica.
        $job->handle(app(RestauranteTicketHtmlService::class), $notifier);

        $effect->refresh();
        $this->assertSame(RestauranteSideEffect::STATUS_DONE, $effect->status);
        $this->assertTrue(Cache::has(app(RestauranteTicketHtmlService::class)->cacheKeyComanda((int) $comanda->id)));

        // Retry tras fallo parcial: status pending otra vez pero dedupe Cache::add evita 2ª notif.
        $effect->update(['status' => RestauranteSideEffect::STATUS_PENDING]);
        $notifier2 = $this->createMock(RestauranteNotifier::class);
        $notifier2->expects($this->never())->method('notify');
        (new ProcesarSideEffectRestauranteJob((int) $effect->id))
            ->handle(app(RestauranteTicketHtmlService::class), $notifier2);
        $effect->refresh();
        $this->assertSame(RestauranteSideEffect::STATUS_DONE, $effect->status);

        $comanda->delete();
        $effect->delete();
        $this->cleanupSesion($sesion);
    }

    public function test_failed_job_marks_failed_and_retry_can_succeed(): void
    {
        $effect = RestauranteSideEffect::create([
            'id_empresa' => $this->empresaId,
            'type' => RestauranteSideEffect::TYPE_COMANDA_TICKET,
            'resource_type' => 'comanda',
            'resource_id' => 999999001,
            'status' => RestauranteSideEffect::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $tickets = $this->createMock(RestauranteTicketHtmlService::class);
        $tickets->expects($this->once())
            ->method('rememberComandaHtml')
            ->willThrowException(new \RuntimeException('boom-render'));

        $job = new ProcesarSideEffectRestauranteJob((int) $effect->id);
        try {
            $job->handle($tickets, app(RestauranteNotifier::class));
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom-render', $e->getMessage());
        }

        $effect->refresh();
        $this->assertSame(RestauranteSideEffect::STATUS_FAILED, $effect->status);
        $this->assertStringContainsString('boom-render', (string) $effect->last_error);

        // Retry path: resource still missing → findOrFail inside render. Use real service after
        // resetting status via dispatcher re-enqueue semantics.
        $effect->update(['status' => RestauranteSideEffect::STATUS_PENDING, 'last_error' => null]);

        $ticketsOk = $this->createMock(RestauranteTicketHtmlService::class);
        $ticketsOk->expects($this->once())
            ->method('rememberComandaHtml')
            ->willReturn('<html>ok</html>');

        $job2 = new ProcesarSideEffectRestauranteJob((int) $effect->id);
        $job2->handle($ticketsOk, app(RestauranteNotifier::class));

        $effect->refresh();
        $this->assertSame(RestauranteSideEffect::STATUS_DONE, $effect->status);

        $effect->delete();
    }

    public function test_rollback_does_not_persist_outbox_row(): void
    {
        try {
            DB::transaction(function () {
                app(RestauranteSideEffectDispatcher::class)->enqueue(
                    RestauranteSideEffect::TYPE_COMANDA_TICKET,
                    'comanda',
                    888001,
                    $this->empresaId
                );
                throw new \RuntimeException('force-rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('force-rollback', $e->getMessage());
        }

        $this->assertDatabaseMissing('restaurante_side_effects', [
            'type' => RestauranteSideEffect::TYPE_COMANDA_TICKET,
            'resource_id' => 888001,
        ]);
    }

    public function test_duplicate_enqueue_does_not_create_second_outbox_row(): void
    {
        Queue::fake();

        $dispatcher = app(RestauranteSideEffectDispatcher::class);
        $dispatcher->enqueueComandaTicket(777001, $this->empresaId);
        $dispatcher->enqueueComandaTicket(777001, $this->empresaId);

        $count = RestauranteSideEffect::query()
            ->where('type', RestauranteSideEffect::TYPE_COMANDA_TICKET)
            ->where('resource_id', 777001)
            ->count();
        $this->assertSame(1, $count);

        RestauranteSideEffect::query()
            ->where('resource_id', 777001)
            ->delete();
    }

    private function crearSesionConItemPendiente(string $prefix): SesionMesa
    {
        $mesa = Mesa::create([
            'id_empresa' => $this->empresaId,
            'numero' => $prefix.'-'.substr((string) microtime(true), -5),
            'capacidad' => 4,
            'estado' => 'ocupada',
            'activo' => true,
        ]);

        $sesion = SesionMesa::create([
            'id_empresa' => $this->empresaId,
            'mesa_id' => $mesa->id,
            'estado' => 'abierta',
            'num_comensales' => 2,
            'opened_at' => now(),
            'mesero_id' => $this->user->id,
            'usuario_id' => $this->user->id,
        ]);

        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaId)
            ->orderBy('id')
            ->first();

        if (! $producto) {
            $this->markTestSkipped('No hay producto para crear ítem de comanda.');
        }

        $producto->genera_comanda = true;
        $producto->destino_comanda = $producto->destino_comanda ?: 'cocina';
        $producto->save();

        OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => (float) ($producto->precio ?? 5),
            'enviado_cocina' => false,
            'enviado_barra' => false,
        ]);

        return $sesion->fresh();
    }

    private function cleanupSesion(SesionMesa $sesion): void
    {
        $mesaId = $sesion->mesa_id;
        $comandaIds = Comanda::where('sesion_id', $sesion->id)->pluck('id');
        if ($comandaIds->isNotEmpty()) {
            RestauranteSideEffect::query()
                ->where('resource_type', 'comanda')
                ->whereIn('resource_id', $comandaIds)
                ->delete();
            Comanda::whereIn('id', $comandaIds)->delete();
        }
        OrdenDetalle::where('sesion_id', $sesion->id)->forceDelete();
        $sesion->delete();
        Mesa::where('id', $mesaId)->delete();
    }
}
