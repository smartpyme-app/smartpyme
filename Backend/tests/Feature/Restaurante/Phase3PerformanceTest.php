<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\MesaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use App\Services\Restaurante\MesaMapaCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 3 — P2: cache mapa, índices performance.
 */
final class Phase3PerformanceTest extends TestCase
{
    private User $user;

    private int $empresaId;

    private MesaMapaCacheService $mapaCache;

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
        $this->mapaCache = app(MesaMapaCacheService::class);
        $this->mapaCache->invalidateEmpresa($this->empresaId);
    }

    protected function tearDown(): void
    {
        try {
            $mesas = Mesa::where('numero', 'like', 'HT3-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
            $this->mapaCache->invalidateEmpresa($this->empresaId);
        } catch (\Throwable) {
        }
        parent::tearDown();
    }

    public function test_mapa_cache_hit_returns_same_payload_without_db_change(): void
    {
        $mesa = $this->crearMesaLibre('HT3-CACHE');
        $this->mapaCache->invalidateEmpresa($this->empresaId);

        $ctrl = app(MesaController::class);
        $req = Request::create('/api/restaurante/mesas', 'GET', ['activo' => true]);

        $r1 = $ctrl->index($req);
        $this->assertSame(200, $r1->getStatusCode());
        $p1 = $r1->getData(true);

        $r2 = $ctrl->index($req);
        $this->assertSame(200, $r2->getStatusCode());
        $p2 = $r2->getData(true);

        $this->assertSame($p1, $p2);
        $this->assertNotNull(collect($p1)->firstWhere('id', $mesa->id));
    }

    public function test_mapa_cache_invalidates_after_abrir_mesa(): void
    {
        $mesa = $this->crearMesaLibre('HT3-INV');
        $this->mapaCache->invalidateEmpresa($this->empresaId);

        $ctrl = app(MesaController::class);
        $req = Request::create('/api/restaurante/mesas', 'GET', ['activo' => true]);

        $before = collect($ctrl->index($req)->getData(true))->firstWhere('id', $mesa->id);
        $this->assertSame('libre', $before['estado'] ?? null);

        $open = Request::create('/api/restaurante/sesiones', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $resp = app(SesionMesaController::class)->store($open);
        $this->assertSame(201, $resp->getStatusCode());

        $after = collect($ctrl->index($req)->getData(true))->firstWhere('id', $mesa->id);
        $this->assertSame('ocupada', $after['estado'] ?? null);
        $this->assertNotNull($after['sesion_activa']['id'] ?? null);
    }

    public function test_fase3_performance_indexes_exist(): void
    {
        if (! Schema::hasTable('orden_detalle_restaurante')) {
            $this->markTestSkipped('orden_detalle no disponible');
        }

        $od = collect(DB::select('SHOW INDEX FROM orden_detalle_restaurante'))->pluck('Key_name');
        $this->assertTrue($od->contains('orden_detalle_rest_sesion_prod_enviado_index'));

        $sm = collect(DB::select('SHOW INDEX FROM restaurante_sesiones_mesa'))->pluck('Key_name');
        $this->assertTrue($sm->contains('restaurante_sesiones_mesa_mesa_id_estado_index'));

        // Cocina index from Fase 2 still present (candidate already satisfied).
        $cmd = collect(DB::select('SHOW INDEX FROM comandas_restaurante'))->pluck('Key_name');
        $this->assertTrue($cmd->contains('comandas_restaurante_id_empresa_estado_created_at_index'));
    }

    private function crearMesaLibre(string $numero): Mesa
    {
        Mesa::where('id_empresa', $this->empresaId)->where('numero', $numero)->delete();

        return Mesa::create([
            'id_empresa' => $this->empresaId,
            'numero' => $numero,
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
            'orden' => 0,
        ]);
    }
}
