<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\MesaController;
use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\ReservaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\RestauranteIdempotencyKey;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 2 — P1: DTO mesas, id_empresa comandas, update ítem, cerrar, idempotency, multi-tenant.
 * Usa DB real (phpunit/.env). Prefijo HT2-* para cleanup.
 */
final class Phase2ApiHardeningTest extends TestCase
{
    private User $userA;

    private User $userB;

    private int $empresaA;

    private int $empresaB;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('restaurante_mesas')) {
            $this->markTestSkipped('Tablas restaurante no disponibles.');
        }
        if (! Schema::hasColumn('comandas_restaurante', 'id_empresa')) {
            $this->markTestSkipped('Migración id_empresa en comandas pendiente.');
        }
        if (! Schema::hasTable('restaurante_idempotency_keys')) {
            $this->markTestSkipped('Migración restaurante_idempotency_keys pendiente.');
        }

        $this->userA = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $this->userA) {
            $this->markTestSkipped('No hay usuario con id_empresa.');
        }
        $this->empresaA = (int) $this->userA->id_empresa;

        $this->userB = User::whereNotNull('id_empresa')
            ->where('id_empresa', '!=', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $this->userB) {
            $this->markTestSkipped('Se necesitan usuarios de al menos 2 empresas para multi-tenant.');
        }
        $this->empresaB = (int) $this->userB->id_empresa;

        Auth::login($this->userA);
    }

    protected function tearDown(): void
    {
        $this->cleanupHt2();
        parent::tearDown();
    }

    public function test_get_mesas_returns_lightweight_dto_keys(): void
    {
        $mesa = $this->crearMesaLibre('HT2-DTO', $this->empresaA);

        $response = app(MesaController::class)->index(Request::create('/api/restaurante/mesas', 'GET'));
        $this->assertSame(200, $response->getStatusCode());

        $rows = $response->getData(true);
        $this->assertIsArray($rows);
        $found = collect($rows)->firstWhere('id', $mesa->id);
        $this->assertNotNull($found, 'DTO debe incluir la mesa creada');

        foreach ([
            'id', 'numero', 'capacidad', 'zona_id', 'zona', 'estado', 'activo', 'orden',
            'tiempo_abierta', 'sesion_activa', 'zona_restaurante', 'reservas_activas',
        ] as $key) {
            $this->assertArrayHasKey($key, $found);
        }

        $this->assertArrayNotHasKey('sesiones', $found);
        $this->assertSame('libre', $found['estado']);
    }

    public function test_comanda_store_sets_id_empresa_and_kitchen_filters_by_it(): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $producto) {
            $this->markTestSkipped('Sin producto para empresa A.');
        }

        $prevGenera = $producto->genera_comanda;
        $prevDestino = $producto->destino_comanda;
        $producto->genera_comanda = true;
        $producto->destino_comanda = 'cocina';
        $producto->save();

        try {
            $mesa = $this->crearMesaLibre('HT2-CMD', $this->empresaA);
            $sesion = $this->abrirSesion($mesa, $this->userA);

            OrdenDetalle::create([
                'sesion_id' => $sesion->id,
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_unitario' => 1,
                'enviado_cocina' => false,
                'enviado_barra' => false,
            ]);

            $resp = app(ComandaController::class)->store(
                Request::create("/api/restaurante/sesiones/{$sesion->id}/comandas", 'POST'),
                $sesion->id
            );
            $this->assertSame(201, $resp->getStatusCode());

            $comanda = Comanda::where('sesion_id', $sesion->id)->latest('id')->first();
            $this->assertNotNull($comanda);
            $this->assertSame($this->empresaA, (int) $comanda->id_empresa);

            Auth::login($this->userB);
            $listB = app(ComandaController::class)->index(Request::create('/api/restaurante/comandas', 'GET'));
            $idsB = collect($listB->getData(true))->pluck('id')->all();
            $this->assertNotContains($comanda->id, $idsB, 'Empresa B no debe ver comanda de A');

            Auth::login($this->userA);
            $listA = app(ComandaController::class)->index(Request::create('/api/restaurante/comandas', 'GET'));
            $idsA = collect($listA->getData(true))->pluck('id')->all();
            $this->assertContains($comanda->id, $idsA);
        } finally {
            $producto->genera_comanda = $prevGenera;
            $producto->destino_comanda = $prevDestino;
            $producto->save();
        }
    }

    public function test_update_item_already_sent_is_blocked(): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $producto) {
            $this->markTestSkipped('Sin producto en empresa A.');
        }

        $mesa = $this->crearMesaLibre('HT2-UPD', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $item = OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 5,
            'enviado_cocina' => true,
            'enviado_barra' => false,
        ]);

        $req = Request::create(
            "/api/restaurante/sesiones/{$sesion->id}/items/{$item->id}",
            'PUT',
            ['cantidad' => 3]
        );
        $resp = app(OrdenDetalleController::class)->update($req, $sesion->id, $item->id);
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame(1.0, (float) $item->fresh()->cantidad);
    }

    public function test_cerrar_blocked_when_items_remain(): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $producto) {
            $this->markTestSkipped('Sin producto en empresa A.');
        }

        $mesa = $this->crearMesaLibre('HT2-CLS', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);
        OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 1,
            'enviado_cocina' => false,
            'enviado_barra' => false,
        ]);

        $this->userA->tipo = 'Ventas';
        $this->userA->save();

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(403, $resp->getStatusCode());
        $body = $resp->getData(true);
        $this->assertTrue($body['requiere_codigo_supervisor'] ?? false);
        $this->assertSame('abierta', $sesion->fresh()->estado);
    }

    public function test_cerrar_force_con_consumo_admin_liquida(): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $producto) {
            $this->markTestSkipped('Sin producto en empresa A.');
        }

        $mesa = $this->crearMesaLibre('HT2-CFA', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);
        OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 3,
            'enviado_cocina' => false,
            'enviado_barra' => false,
        ]);
        PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 3,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 3,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT2-CFA-1',
        ]);

        $this->userA->tipo = 'Administrador';
        $this->userA->save();

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('cerrada', $sesion->fresh()->estado);
        $this->assertSame(0, OrdenDetalle::where('sesion_id', $sesion->id)->count());
        $this->assertSame(0, PreCuenta::where('sesion_id', $sesion->id)->where('estado', 'pendiente')->count());
    }

    public function test_cerrar_empty_session_ok(): void
    {
        $mesa = $this->crearMesaLibre('HT2-CLE', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('cerrada', $sesion->fresh()->estado);
        $this->assertSame('libre', $mesa->fresh()->estado);
    }

    public function test_cerrar_anula_precuenta_pendiente_sin_consumo(): void
    {
        $mesa = $this->crearMesaLibre('HT2-PCP', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);
        PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 0,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 0,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT2-PCP-1',
        ]);

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('cerrada', $sesion->fresh()->estado);
        $this->assertSame(0, PreCuenta::where('sesion_id', $sesion->id)->where('estado', 'pendiente')->count());
    }

    public function test_cerrar_blocked_when_precuenta_pendiente_con_consumo(): void
    {
        $producto = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->orderBy('id')
            ->first();
        if (! $producto) {
            $this->markTestSkipped('Sin producto en empresa A.');
        }

        $mesa = $this->crearMesaLibre('HT2-PCC', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);
        OrdenDetalle::create([
            'sesion_id' => $sesion->id,
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio_unitario' => 5,
            'enviado_cocina' => false,
            'enviado_barra' => false,
        ]);
        PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 5,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 5,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT2-PCC-1',
        ]);

        $this->userA->tipo = 'Ventas';
        $this->userA->save();

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(403, $resp->getStatusCode());
        $this->assertSame('abierta', $sesion->fresh()->estado);
    }

    public function test_cerrar_blocked_when_comanda_activa_sin_items(): void
    {
        $mesa = $this->crearMesaLibre('HT2-CCA', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);

        Comanda::create([
            'id_empresa' => $this->empresaA,
            'sesion_id' => $sesion->id,
            'numero_comanda' => 'C-HT2-CCA-1-C',
            'estado' => 'pendiente',
            'destino' => 'cocina',
            'enviado_at' => now(),
        ]);

        $resp = app(SesionMesaController::class)->cerrar(Request::create('/cerrar', 'PUT'), $sesion->id);
        $this->assertSame(422, $resp->getStatusCode());
        $body = $resp->getData(true);
        $this->assertStringContainsString('comandas', (string) ($body['error'] ?? ''));
        $this->assertSame('abierta', $sesion->fresh()->estado);
    }

    public function test_idempotency_key_open_mesa_replays_same_session(): void
    {
        $mesa = $this->crearMesaLibre('HT2-IDM', $this->empresaA);
        $key = 'ht2-open-'.uniqid('', true);

        $req1 = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $req1->headers->set('Idempotency-Key', $key);

        $r1 = app(SesionMesaController::class)->store($req1);
        $this->assertSame(201, $r1->getStatusCode());
        $body1 = $r1->getData(true);
        $sesionId = (int) $body1['id'];

        $req2 = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 9,
        ]);
        $req2->headers->set('Idempotency-Key', $key);

        $r2 = app(SesionMesaController::class)->store($req2);
        $this->assertSame(201, $r2->getStatusCode());
        $body2 = $r2->getData(true);
        $this->assertSame($sesionId, (int) $body2['id']);
        $this->assertSame(2, (int) $body2['num_comensales']);

        $activas = SesionMesa::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->count();
        $this->assertSame(1, $activas);

        $this->assertTrue(
            RestauranteIdempotencyKey::where('idempotency_key', $key)
                ->where('operation', 'abrir_mesa')
                ->where('status', 'completed')
                ->exists()
        );
    }

    public function test_idempotency_key_in_progress_returns_409(): void
    {
        $mesa = $this->crearMesaLibre('HT2-IDP', $this->empresaA);
        $key = 'ht2-prog-'.uniqid('', true);

        RestauranteIdempotencyKey::create([
            'id_empresa' => $this->empresaA,
            'user_id' => $this->userA->id,
            'operation' => 'abrir_mesa',
            'idempotency_key' => $key,
            'status' => 'processing',
            'expires_at' => now()->addHour(),
        ]);

        $req = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 1,
        ]);
        $req->headers->set('Idempotency-Key', $key);

        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(409, $resp->getStatusCode());
        $this->assertSame(0, SesionMesa::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->count());
    }

    public function test_idempotency_expired_key_can_be_reused(): void
    {
        $mesa = $this->crearMesaLibre('HT2-IDX', $this->empresaA);
        $key = 'ht2-exp-'.uniqid('', true);

        RestauranteIdempotencyKey::create([
            'id_empresa' => $this->empresaA,
            'user_id' => $this->userA->id,
            'operation' => 'abrir_mesa',
            'idempotency_key' => $key,
            'status' => 'completed',
            'response_code' => 201,
            'response_body' => json_encode(['id' => 999999, 'num_comensales' => 1]),
            'expires_at' => now()->subHour(),
        ]);

        $req = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 3,
        ]);
        $req->headers->set('Idempotency-Key', $key);

        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $resp->getStatusCode());
        $body = $resp->getData(true);
        $this->assertNotSame(999999, (int) $body['id']);
        $this->assertSame(3, (int) $body['num_comensales']);
    }

    public function test_cross_tenant_cannot_open_foreign_mesa(): void
    {
        $mesaB = $this->crearMesaLibre('HT2-XTA', $this->empresaB);

        Auth::login($this->userA);
        $req = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesaB->id,
            'num_comensales' => 1,
        ]);

        try {
            app(SesionMesaController::class)->store($req);
            $this->fail('Debía fallar validación/exists al abrir mesa de otra empresa');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('mesa_id', $e->errors());
        }

        $this->assertSame(0, SesionMesa::where('mesa_id', $mesaB->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->count());
    }

    public function test_cross_tenant_cannot_read_foreign_sesion(): void
    {
        $mesa = $this->crearMesaLibre('HT2-XTB', $this->empresaA);
        $sesion = $this->abrirSesion($mesa, $this->userA);

        Auth::login($this->userB);
        try {
            app(SesionMesaController::class)->show($sesion->id);
            $this->fail('Empresa B no debe leer sesión de A');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_cross_tenant_cannot_reservar_foreign_mesa(): void
    {
        $mesaB = $this->crearMesaLibre('HT2-XTR', $this->empresaB);

        Auth::login($this->userA);
        $req = Request::create('/api/restaurante/reservas', 'POST', [
            'mesa_id' => $mesaB->id,
            'fecha_reserva' => now()->toDateString(),
            'hora_reserva' => '19:00',
            'cliente_nombre' => 'HT2 Cross',
        ]);

        try {
            app(ReservaController::class)->store($req);
            $this->fail('Debía fallar validación al reservar mesa de otra empresa');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('mesa_id', $e->errors());
        }
    }

    private function crearMesaLibre(string $numero, int $empresaId): Mesa
    {
        Mesa::where('id_empresa', $empresaId)->where('numero', $numero)->delete();

        return Mesa::create([
            'id_empresa' => $empresaId,
            'numero' => $numero,
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
            'orden' => 0,
        ]);
    }

    private function abrirSesion(Mesa $mesa, User $user): SesionMesa
    {
        Auth::login($user);
        $req = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $resp->getStatusCode());

        return SesionMesa::findOrFail($resp->getData(true)['id']);
    }

    private function cleanupHt2(): void
    {
        try {
            $mesas = Mesa::where('numero', 'like', 'HT2-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    $comandaIds = Comanda::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($comandaIds->isNotEmpty()) {
                        \DB::table('comanda_detalle_restaurante')->whereIn('comanda_id', $comandaIds)->delete();
                        Comanda::whereIn('id', $comandaIds)->delete();
                    }
                    PreCuenta::whereIn('sesion_id', $sesionIds)->delete();
                    OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->forceDelete();
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
            RestauranteIdempotencyKey::where('idempotency_key', 'like', 'ht2-%')->delete();
            \App\Models\Restaurante\Reserva::where('cliente_nombre', 'like', 'HT2%')->delete();
        } catch (\Throwable) {
            // cleanup best-effort
        }
    }
}
