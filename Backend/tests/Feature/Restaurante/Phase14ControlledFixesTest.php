<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use App\Http\Controllers\Api\Restaurante\ReservaController;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Reserva;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 14 — correcciones controladas: default fecha reservas, regresión cocina/pedidos/tenant.
 * Prefijo HT14-* para cleanup.
 */
final class Phase14ControlledFixesTest extends TestCase
{
    private User $userA;

    private User $userB;

    private int $empresaA;

    private int $empresaB;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('reservas_restaurante') || ! Schema::hasTable('comandas_restaurante')) {
            $this->markTestSkipped('Tablas restaurante no disponibles.');
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
            $this->markTestSkipped('Se necesitan ≥2 empresas para cross-tenant.');
        }
        $this->empresaB = (int) $this->userB->id_empresa;

        Auth::login($this->userA);
    }

    protected function tearDown(): void
    {
        $this->cleanupHt14();
        parent::tearDown();
    }

    public function test_reservas_index_defaults_to_today_without_fecha(): void
    {
        $mesa = $this->crearMesaLibre('HT14-R1');
        Reserva::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $this->empresaA,
            'fecha_reserva' => now()->toDateString(),
            'hora_reserva' => '12:00:00',
            'cliente_nombre' => 'HT14 Hoy',
            'estado' => 'pendiente',
            'usuario_id' => $this->userA->id,
        ]);
        Reserva::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $this->empresaA,
            'fecha_reserva' => now()->subDay()->toDateString(),
            'hora_reserva' => '12:00:00',
            'cliente_nombre' => 'HT14 Ayer',
            'estado' => 'pendiente',
            'usuario_id' => $this->userA->id,
        ]);

        $resp = app(ReservaController::class)->index(Request::create('/api/restaurante/reservas', 'GET'));
        $this->assertSame(200, $resp->getStatusCode());
        $nombres = collect($resp->getData(true))->pluck('cliente_nombre');
        $this->assertTrue($nombres->contains('HT14 Hoy'));
        $this->assertFalse($nombres->contains('HT14 Ayer'));
    }

    public function test_reservas_index_todas_includes_other_days(): void
    {
        $mesa = $this->crearMesaLibre('HT14-R2');
        Reserva::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $this->empresaA,
            'fecha_reserva' => now()->subDays(3)->toDateString(),
            'hora_reserva' => '18:00:00',
            'cliente_nombre' => 'HT14 Vieja',
            'estado' => 'pendiente',
            'usuario_id' => $this->userA->id,
        ]);

        $req = Request::create('/api/restaurante/reservas', 'GET', ['todas' => 1]);
        $resp = app(ReservaController::class)->index($req);
        $nombres = collect($resp->getData(true))->pluck('cliente_nombre');
        $this->assertTrue($nombres->contains('HT14 Vieja'));
    }

    public function test_reservas_index_explicit_fecha_filter(): void
    {
        $mesa = $this->crearMesaLibre('HT14-R3');
        $manana = now()->addDay()->toDateString();
        Reserva::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $this->empresaA,
            'fecha_reserva' => $manana,
            'hora_reserva' => '10:00:00',
            'cliente_nombre' => 'HT14 Manana',
            'estado' => 'pendiente',
            'usuario_id' => $this->userA->id,
        ]);

        $req = Request::create('/api/restaurante/reservas', 'GET', ['fecha' => $manana]);
        $resp = app(ReservaController::class)->index($req);
        $nombres = collect($resp->getData(true))->pluck('cliente_nombre');
        $this->assertTrue($nombres->contains('HT14 Manana'));
    }

    public function test_reservas_cross_tenant_isolation(): void
    {
        $mesaB = $this->crearMesaLibre('HT14-XB', $this->empresaB);
        Reserva::create([
            'mesa_id' => $mesaB->id,
            'id_empresa' => $this->empresaB,
            'fecha_reserva' => now()->toDateString(),
            'hora_reserva' => '11:00:00',
            'cliente_nombre' => 'HT14 OtherEmp',
            'estado' => 'pendiente',
            'usuario_id' => $this->userB->id,
        ]);

        Auth::login($this->userA);
        $resp = app(ReservaController::class)->index(Request::create('/api/reservas', 'GET', ['todas' => 1]));
        $nombres = collect($resp->getData(true))->pluck('cliente_nombre');
        $this->assertFalse($nombres->contains('HT14 OtherEmp'));
    }

    public function test_comandas_index_excludes_servido_when_enum_allows(): void
    {
        if (! $this->comandasEnumIncludesServido()) {
            $this->markTestSkipped('Migración servido no aplicada en DB de tests (aplicar 2026_08_04_120000_add_servido…).');
        }

        $mesa = $this->crearMesaLibre('HT14-C1');
        $sesion = SesionMesa::create([
            'mesa_id' => $mesa->id,
            'id_empresa' => $this->empresaA,
            'estado' => 'abierta',
            'usuario_id' => $this->userA->id,
            'num_comensales' => 2,
            'opened_at' => now(),
        ]);

        $activa = Comanda::create([
            'sesion_id' => $sesion->id,
            'id_empresa' => $this->empresaA,
            'numero_comanda' => 'HT14-ACT',
            'destino' => 'cocina',
            'estado' => 'pendiente',
            'enviado_at' => now(),
        ]);
        $servida = Comanda::create([
            'sesion_id' => $sesion->id,
            'id_empresa' => $this->empresaA,
            'numero_comanda' => 'HT14-SRV',
            'destino' => 'cocina',
            'estado' => 'servido',
            'enviado_at' => now(),
        ]);

        $resp = app(ComandaController::class)->index(Request::create('/api/restaurante/comandas', 'GET'));
        $ids = collect($resp->getData(true))->pluck('id');
        $this->assertTrue($ids->contains($activa->id));
        $this->assertFalse($ids->contains($servida->id));
    }

    public function test_comandas_index_cross_tenant_isolation(): void
    {
        $mesaB = $this->crearMesaLibre('HT14-XC', $this->empresaB);
        $sesionB = SesionMesa::create([
            'mesa_id' => $mesaB->id,
            'id_empresa' => $this->empresaB,
            'estado' => 'abierta',
            'usuario_id' => $this->userB->id,
            'num_comensales' => 1,
            'opened_at' => now(),
        ]);
        $foreign = Comanda::create([
            'sesion_id' => $sesionB->id,
            'id_empresa' => $this->empresaB,
            'numero_comanda' => 'HT14-FOR',
            'destino' => 'cocina',
            'estado' => 'pendiente',
            'enviado_at' => now(),
        ]);

        Auth::login($this->userA);
        $resp = app(ComandaController::class)->index(Request::create('/api/restaurante/comandas', 'GET'));
        $ids = collect($resp->getData(true))->pluck('id');
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_pedidos_paginate_caps_at_100(): void
    {
        if (! Schema::hasTable('restaurante_pedidos')) {
            $this->markTestSkipped('Tabla restaurante_pedidos no disponible.');
        }

        $req = Request::create('/api/restaurante/pedidos', 'GET', ['paginate' => 999, 'page' => 1]);
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(PedidoRestauranteController::class)->index($req);
        $this->assertSame(200, $resp->getStatusCode());
        $data = $resp->getData(true);
        $this->assertArrayHasKey('per_page', $data);
        $this->assertSame(100, (int) $data['per_page']);
    }

    private function comandasEnumIncludesServido(): bool
    {
        $row = DB::selectOne("SHOW COLUMNS FROM comandas_restaurante LIKE 'estado'");
        $type = strtolower((string) ($row->Type ?? ''));

        return str_contains($type, "'servido'");
    }

    private function crearMesaLibre(string $numero, ?int $empresaId = null): Mesa
    {
        $empresaId ??= $this->empresaA;
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

    private function cleanupHt14(): void
    {
        try {
            Reserva::where('cliente_nombre', 'like', 'HT14%')->delete();
            $mesas = Mesa::where('numero', 'like', 'HT14-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    Comanda::whereIn('sesion_id', $sesionIds)->delete();
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
        } catch (\Throwable) {
            // best-effort
        }
    }
}
