<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Restaurante\ConcurrentActorRunner;
use Tests\TestCase;

/**
 * Fase 11 — cierra gaps del mínimo del plan §13 no cubiertos por F1–6.
 * Prefijo HT11-* para cleanup. Sin load/k6. Sin cambios de código productivo.
 */
final class Phase11SuiteCoverageTest extends TestCase
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
        $this->cleanupHt11();
        parent::tearDown();
    }

    /** Plan §13.1 — abrir mesa (happy path). */
    public function test_abrir_mesa_crea_sesion_activa(): void
    {
        $mesa = $this->crearMesaLibre('HT11-OPEN');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        $this->assertSame('abierta', $sesion->estado);
        $this->assertSame($mesa->id, (int) $sesion->mesa_id);
        $this->assertSame(
            1,
            SesionMesa::where('mesa_id', $mesa->id)->whereIn('estado', ['abierta', 'pre_cuenta'])->count()
        );
    }

    /** Plan §13.3 + §13.7 — agregar producto y solicitar cuenta. */
    public function test_agregar_producto_y_solicitar_cuenta_crea_precuenta_pendiente(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $mesa = $this->crearMesaLibre('HT11-PC');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        Auth::login($this->userA);
        $add = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'notas' => 'ht11-pc',
        ]);
        $add->setUserResolver(fn () => $this->userA);
        $addResp = app(OrdenDetalleController::class)->store($add, $sesion->id);
        $this->assertSame(201, $addResp->getStatusCode(), $addResp->getContent());

        $gen = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id.'/pre-cuenta', 'POST', []);
        $gen->setUserResolver(fn () => $this->userA);
        $genResp = app(PreCuentaController::class)->generar($gen, $sesion->id);
        $this->assertSame(201, $genResp->getStatusCode(), $genResp->getContent());

        $pendientes = PreCuenta::where('sesion_id', $sesion->id)->where('estado', 'pendiente')->count();
        $this->assertSame(1, $pendientes);

        $sesion->refresh();
        $this->assertContains($sesion->estado, ['abierta', 'pre_cuenta']);
    }

    /** Plan §13.9 — facturar simultáneo sin doble liquidación. */
    public function test_two_concurrent_marcar_facturada_liquidate_once(): void
    {
        $ventaId = $this->facturaIdValida($this->empresaA);
        if (! $ventaId) {
            $this->markTestSkipped('No hay venta para factura_id.');
        }

        $producto = $this->productoEmpresa($this->empresaA);
        $mesa = $this->crearMesaLibre('HT11-FAC');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        Auth::login($this->userA);
        $add = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'notas' => 'ht11-fac',
        ]);
        $add->setUserResolver(fn () => $this->userA);
        app(OrdenDetalleController::class)->store($add, $sesion->id);
        $item = OrdenDetalle::where('sesion_id', $sesion->id)->where('notas', 'ht11-fac')->firstOrFail();

        $pc = PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 10,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 10,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT11-'.$mesa->id,
        ]);
        PreCuentaOrdenDetalle::create([
            'pre_cuenta_id' => $pc->id,
            'orden_detalle_id' => $item->id,
            'cantidad' => 2,
        ]);

        $results = ConcurrentActorRunner::run('tests/Support/Restaurante/concurrent_actor.php', [
            [
                'action' => 'marcar_facturada',
                'user_id' => $this->userA->id,
                'pre_cuenta_id' => $pc->id,
                'factura_id' => $ventaId,
            ],
            [
                'action' => 'marcar_facturada',
                'user_id' => $this->userA->id,
                'pre_cuenta_id' => $pc->id,
                'factura_id' => $ventaId,
            ],
        ]);

        $ok = collect($results)->filter(fn ($r) => ($r['json']['status'] ?? 0) === 200)->count();
        $this->assertGreaterThanOrEqual(1, $ok, 'Al menos un marcar 200. '.json_encode($results));

        $pc->refresh();
        $this->assertSame('facturada', $pc->estado);
        $this->assertSame(
            0,
            OrdenDetalle::where('sesion_id', $sesion->id)->where('notas', 'ht11-fac')->count(),
            'Línea liquidada una sola vez. Results='.json_encode($results)
        );
        $this->assertSame(1, PreCuenta::whereKey($pc->id)->where('estado', 'facturada')->count());
    }

    /** Plan §13.14–17 — cross-tenant comanda. */
    public function test_cross_tenant_cannot_update_foreign_comanda(): void
    {
        $mesa = $this->crearMesaLibre('HT11-CMD');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $comanda = Comanda::create([
            'sesion_id' => $sesion->id,
            'id_empresa' => $this->empresaA,
            'numero_comanda' => 'HT11-C-1',
            'estado' => 'pendiente',
            'destino' => 'cocina',
            'enviado_at' => now(),
        ]);

        Auth::login($this->userB);
        $req = Request::create('/x', 'PUT', ['estado' => 'preparando']);
        $req->setUserResolver(fn () => $this->userB);

        try {
            app(ComandaController::class)->actualizarEstado($req, $comanda->id);
            $this->fail('Debía fallar acceso cross-tenant a comanda');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $comanda->refresh();
        $this->assertSame('pendiente', $comanda->estado);
        $this->assertSame($this->empresaA, (int) $comanda->id_empresa);
        $this->assertNotSame($this->empresaB, (int) $comanda->id_empresa);
    }

    /** Plan §13.14–17 — cross-tenant precuenta. */
    public function test_cross_tenant_cannot_read_foreign_precuenta(): void
    {
        $mesa = $this->crearMesaLibre('HT11-XP');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $pc = PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 1,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 1,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT11-X-'.$mesa->id,
        ]);

        Auth::login($this->userB);

        try {
            app(PreCuentaController::class)->show($pc->id);
            $this->fail('Debía fallar lectura cross-tenant de precuenta');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    private function crearMesaLibre(string $prefix): Mesa
    {
        return Mesa::create([
            'id_empresa' => $this->empresaA,
            'id_sucursal' => $this->userA->id_sucursal,
            'numero' => $prefix.'-'.substr(bin2hex(random_bytes(3)), 0, 6),
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
            'orden' => 0,
        ]);
    }

    private function abrirSesion(Mesa $mesa, User $user): SesionMesa
    {
        Auth::login($user);
        $req = Request::create('/api/restaurante/sesiones-mesa', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $req->setUserResolver(fn () => $user);
        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $resp->getStatusCode(), $resp->getContent());

        return SesionMesa::findOrFail(json_decode($resp->getContent(), true)['id']);
    }

    private function productoEmpresa(int $empresaId): Producto
    {
        $p = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->where('enable', 1)
            ->orderBy('id')
            ->first();
        if (! $p) {
            $this->markTestSkipped('Sin producto enable para empresa.');
        }

        return $p;
    }

    private function facturaIdValida(int $empresaId): ?int
    {
        if (! Schema::hasTable('ventas')) {
            return null;
        }
        $id = DB::table('ventas')->where('id_empresa', $empresaId)->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    private function cleanupHt11(): void
    {
        try {
            $mesas = Mesa::where('numero', 'like', 'HT11-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    $pcIds = PreCuenta::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($pcIds->isNotEmpty()) {
                        PreCuentaOrdenDetalle::whereIn('pre_cuenta_id', $pcIds)->delete();
                        PreCuenta::whereIn('id', $pcIds)->delete();
                    }
                    $comandaIds = Comanda::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($comandaIds->isNotEmpty()) {
                        ComandaDetalle::whereIn('comanda_id', $comandaIds)->delete();
                        Comanda::whereIn('id', $comandaIds)->delete();
                    }
                    OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->forceDelete();
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
        } catch (\Throwable) {
            // best-effort
        }
    }
}
