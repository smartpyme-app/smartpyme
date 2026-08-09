<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Restaurante\PedidoRestauranteDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Restaurante\ConcurrentActorRunner;
use Tests\TestCase;

/**
 * Tests de integridad / concurrencia P0.
 *
 * Escenarios concurrentes usan procesos OS independientes (ConcurrentActorRunner),
 * NO una transacción envolvente del test padre.
 *
 * Requiere MariaDB/MySQL real (phpunit usa .env). Limpia datos HT-* creados.
 */
final class ConcurrencyIntegrityTest extends TestCase
{
    private User $user;

    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('restaurante_mesas')) {
            $this->markTestSkipped('Tablas restaurante no disponibles en este entorno.');
        }

        $this->user = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $this->user) {
            $this->markTestSkipped('No hay usuario con id_empresa para tests.');
        }
        $this->empresaId = (int) $this->user->id_empresa;
        Auth::login($this->user);
    }

    public function test_unique_active_session_index_exists_after_migration(): void
    {
        if (! $this->hasUniqueActiveSessionIndex()) {
            $this->markTestSkipped('Migración uq_restaurante_mesa_sesion_activa aún no aplicada.');
        }

        $this->assertTrue($this->hasUniqueActiveSessionIndex());
        // MariaDB 10.11 (prod): columna GENERATED STORED.
        // MySQL 8+/9 (dev local): functional index sin columna (fallback).
        $v = (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');
        if (stripos($v, 'mariadb') !== false) {
            $this->assertTrue(
                Schema::hasColumn('restaurante_sesiones_mesa', 'mesa_sesion_activa_id'),
                'En MariaDB la solución final requiere columna generada mesa_sesion_activa_id.'
            );
        }
    }

    public function test_two_concurrent_open_mesa_produce_single_active_session(): void
    {
        if (! $this->hasUniqueActiveSessionIndex()) {
            $this->markTestSkipped('Requiere migración de unique sesión activa.');
        }

        $mesa = $this->crearMesaLibre('HT-OPEN');

        $results = ConcurrentActorRunner::run('tests/Support/Restaurante/concurrent_actor.php', [
            [
                'action' => 'abrir_mesa',
                'user_id' => $this->user->id,
                'mesa_id' => $mesa->id,
                'num_comensales' => 2,
            ],
            [
                'action' => 'abrir_mesa',
                'user_id' => $this->user->id,
                'mesa_id' => $mesa->id,
                'num_comensales' => 3,
            ],
        ]);

        $ok = collect($results)->filter(fn ($r) => ($r['json']['status'] ?? 0) === 201)->count();
        $conflict = collect($results)->filter(fn ($r) => ($r['json']['status'] ?? 0) === 422)->count();

        $activas = SesionMesa::where('mesa_id', $mesa->id)
            ->whereIn('estado', ['abierta', 'pre_cuenta'])
            ->count();

        $this->assertSame(1, $activas, 'Debe existir exactamente una sesión activa. Results=' . json_encode($results));
        $this->assertSame(1, $ok, 'Exactamente un actor debe crear (201).');
        $this->assertSame(1, $conflict, 'El otro actor debe recibir 422.');

        $this->cleanupMesa($mesa);
    }

    public function test_retry_open_mesa_after_success_returns_422_without_second_session(): void
    {
        $mesa = $this->crearMesaLibre('HT-RETRY-OPEN');
        Auth::login($this->user);

        $req = Request::create('/api/restaurante/sesiones-mesa', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $req->setUserResolver(fn () => $this->user);
        $r1 = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $r1->getStatusCode());

        $r2 = app(SesionMesaController::class)->store($req);
        $this->assertSame(422, $r2->getStatusCode());

        $this->assertSame(
            1,
            SesionMesa::where('mesa_id', $mesa->id)->whereIn('estado', ['abierta', 'pre_cuenta'])->count()
        );

        $this->cleanupMesa($mesa);
    }

    public function test_two_concurrent_add_same_product_fuse_to_one_line(): void
    {
        $mesa = $this->crearMesaLibre('HT-ADD');
        $sesion = $this->abrirSesion($mesa);
        $producto = $this->productoEmpresa();

        $results = ConcurrentActorRunner::run('tests/Support/Restaurante/concurrent_actor.php', [
            [
                'action' => 'agregar_item',
                'user_id' => $this->user->id,
                'sesion_id' => $sesion->id,
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'notas' => 'ht-fuse',
            ],
            [
                'action' => 'agregar_item',
                'user_id' => $this->user->id,
                'sesion_id' => $sesion->id,
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'notas' => 'ht-fuse',
            ],
        ]);

        $ok = collect($results)->filter(fn ($r) => ($r['json']['ok'] ?? false) === true)->count();
        $lineas = OrdenDetalle::where('sesion_id', $sesion->id)
            ->where('producto_id', $producto->id)
            ->where('notas', 'ht-fuse')
            ->get();

        $this->assertSame(2, $ok, 'Ambos adds deben completar. ' . json_encode($results));
        $this->assertCount(1, $lineas, 'Debe fusionarse en una sola línea. ' . json_encode($results));
        $this->assertEqualsWithDelta(2.0, (float) $lineas->first()->cantidad, 0.001);

        $this->cleanupMesa($mesa);
    }

    public function test_two_concurrent_send_comanda_create_single_comanda_for_pending_lines(): void
    {
        $mesa = $this->crearMesaLibre('HT-CMD');
        $sesion = $this->abrirSesion($mesa);
        $producto = $this->productoEmpresa();

        // Forzar genera_comanda para este producto en la sesión de test (rollback de valor al cleanup no crítico).
        $prevGenera = $producto->genera_comanda;
        $prevDestino = $producto->destino_comanda;
        Producto::withoutGlobalScope('empresa')->whereKey($producto->id)->update([
            'genera_comanda' => 1,
            'destino_comanda' => 'cocina',
        ]);

        Auth::login($this->user);
        $req = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'notas' => 'ht-cmd',
        ]);
        $req->setUserResolver(fn () => $this->user);
        $add = app(OrdenDetalleController::class)->store($req, $sesion->id);
        $this->assertTrue($add->getStatusCode() < 400, $add->getContent());

        $results = ConcurrentActorRunner::run('tests/Support/Restaurante/concurrent_actor.php', [
            [
                'action' => 'enviar_comanda',
                'user_id' => $this->user->id,
                'sesion_id' => $sesion->id,
            ],
            [
                'action' => 'enviar_comanda',
                'user_id' => $this->user->id,
                'sesion_id' => $sesion->id,
            ],
        ]);

        $created = collect($results)->filter(fn ($r) => ($r['json']['status'] ?? 0) === 201)->count();
        $rejected = collect($results)->filter(fn ($r) => in_array(($r['json']['status'] ?? 0), [422, 409], true))->count();
        $comandas = Comanda::where('sesion_id', $sesion->id)->where('destino', 'cocina')->count();

        $this->assertSame(1, $comandas, 'Solo una comanda cocina. Results=' . json_encode($results));
        $this->assertSame(1, $created);
        $this->assertSame(1, $rejected);

        Producto::withoutGlobalScope('empresa')->whereKey($producto->id)->update([
            'genera_comanda' => $prevGenera,
            'destino_comanda' => $prevDestino,
        ]);

        $this->cleanupMesa($mesa);
    }

    public function test_two_concurrent_confirmar_pedido_single_inventory_exit(): void
    {
        if (! Schema::hasTable('restaurante_pedidos') || ! Schema::hasTable('kardexs')) {
            $this->markTestSkipped('Tablas restaurante_pedidos/kardexs no disponibles.');
        }

        $bodegaId = (int) ($this->user->id_bodega ?: 0);
        if ($bodegaId <= 0) {
            $this->markTestSkipped('Usuario sin id_bodega; fixture requerida para confirmar pedido.');
        }

        $inv = Inventario::query()
            ->where('id_bodega', $bodegaId)
            ->where('stock', '>=', 5)
            ->whereHas('producto', function ($q) {
                $q->withoutGlobalScope('empresa')
                    ->where('id_empresa', $this->empresaId)
                    ->where('enable', 1)
                    ->where('tipo', '!=', 'Servicio');
            })
            ->with(['producto' => fn ($q) => $q->withoutGlobalScope('empresa')])
            ->orderBy('id_producto')
            ->first();

        if (! $inv || ! $inv->producto) {
            $this->markTestSkipped('No hay producto con stock>=5 en bodega del usuario (fixture inventario).');
        }

        $producto = $inv->producto;
        $qty = 1.0;
        $stockAntes = (float) $inv->stock;

        $pedido = PedidoRestaurante::create([
            'id_empresa' => $this->empresaId,
            'id_sucursal' => $this->user->id_sucursal,
            'id_bodega' => $bodegaId,
            'usuario_id' => $this->user->id,
            'fecha' => now()->toDateString(),
            'canal' => 'ht-conc-test',
            'estado' => 'borrador',
            'subtotal' => $producto->precio,
            'descuento' => 0,
            'total' => $producto->precio,
        ]);

        PedidoRestauranteDetalle::create([
            'pedido_id' => $pedido->id,
            'producto_id' => $producto->id,
            'cantidad' => $qty,
            'precio' => $producto->precio,
            'descuento' => 0,
            'subtotal' => $producto->precio,
            'total' => $producto->precio,
            'notas' => 'ht-conc-inv',
        ]);

        $kardexAntes = (int) DB::table('kardexs')
            ->where('referencia', $pedido->id)
            ->where('detalle', 'Pedido pendiente de facturar')
            ->count();

        $results = ConcurrentActorRunner::run('tests/Support/Restaurante/concurrent_actor.php', [
            [
                'action' => 'confirmar_pedido',
                'user_id' => $this->user->id,
                'pedido_id' => $pedido->id,
                'id_bodega' => $bodegaId,
            ],
            [
                'action' => 'confirmar_pedido',
                'user_id' => $this->user->id,
                'pedido_id' => $pedido->id,
                'id_bodega' => $bodegaId,
            ],
        ]);

        $ok = collect($results)->filter(fn ($r) => ($r['json']['ok'] ?? false) === true)->count();
        $pedido->refresh();
        $kardexDespues = (int) DB::table('kardexs')
            ->where('referencia', $pedido->id)
            ->where('detalle', 'Pedido pendiente de facturar')
            ->count();
        $stockDespues = (float) Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $bodegaId)
            ->value('stock');

        $this->assertGreaterThanOrEqual(1, $ok, 'Al menos un confirmar OK. ' . json_encode($results));
        $this->assertSame('pendiente_facturar', $pedido->estado);
        $this->assertNotNull($pedido->inventario_descontado_at, 'Debe setear inventario_descontado_at una vez.');
        $this->assertSame(
            1,
            PedidoRestaurante::whereKey($pedido->id)->whereNotNull('inventario_descontado_at')->count()
        );
        $this->assertSame(
            $kardexAntes + 1,
            $kardexDespues,
            'Exactamente una salida kardex "Pedido pendiente de facturar". Results=' . json_encode($results)
        );
        $this->assertEqualsWithDelta(
            $stockAntes - $qty,
            $stockDespues,
            0.001,
            'Stock debe bajar exactamente la cantidad pedida (una vez).'
        );

        // Cleanup: anular para revertir stock (usa servicio idempotente)
        Auth::login($this->user);
        try {
            app(\App\Http\Controllers\Api\Restaurante\PedidoRestauranteController::class)
                ->anular($pedido->id);
        } catch (\Throwable $e) {
            // best-effort cleanup
        }
        PedidoRestauranteDetalle::where('pedido_id', $pedido->id)->delete();
        // no borrar kardex histórico; anular debería crear movimiento inverso
        $pedido->delete();
    }

    public function test_marcar_facturada_retry_is_idempotent_without_double_liquidate(): void
    {
        $mesa = $this->crearMesaLibre('HT-FAC');
        $sesion = $this->abrirSesion($mesa);
        $producto = $this->productoEmpresa();

        Auth::login($this->user);
        $req = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'notas' => 'ht-fac',
        ]);
        $req->setUserResolver(fn () => $this->user);
        app(OrdenDetalleController::class)->store($req, $sesion->id);

        $item = OrdenDetalle::where('sesion_id', $sesion->id)->where('notas', 'ht-fac')->firstOrFail();

        $pc = PreCuenta::create([
            'sesion_id' => $sesion->id,
            'subtotal' => 10,
            'descuento' => 0,
            'impuesto' => 0,
            'propina_monto' => 0,
            'propina_porcentaje_aplicado' => 0,
            'total' => 10,
            'estado' => 'pendiente',
            'numero_pre_cuenta' => 'PC-HT-' . $mesa->id,
        ]);
        PreCuentaOrdenDetalle::create([
            'pre_cuenta_id' => $pc->id,
            'orden_detalle_id' => $item->id,
            'cantidad' => 2,
        ]);

        $ventaId = $this->facturaIdValida();
        if (! $ventaId) {
            $this->cleanupMesa($mesa);
            $this->markTestSkipped('No hay venta existente para factura_id (exists:ventas,id).');
        }

        $mark = function () use ($pc, $ventaId) {
            $r = Request::create('/x', 'PUT', ['factura_id' => $ventaId]);
            $r->setUserResolver(fn () => $this->user);

            return app(PreCuentaController::class)->marcarFacturada($r, $pc->id);
        };

        Auth::login($this->user);
        $r1 = $mark();
        $this->assertSame(200, $r1->getStatusCode(), $r1->getContent());
        $r2 = $mark();
        $this->assertSame(200, $r2->getStatusCode(), $r2->getContent());

        $pc->refresh();
        $this->assertSame('facturada', $pc->estado);
        $this->assertSame(
            0,
            OrdenDetalle::where('sesion_id', $sesion->id)->where('notas', 'ht-fac')->count(),
            'Línea debe liquidarse una sola vez (soft-deleted).'
        );

        $this->cleanupMesa($mesa);
    }

    private function hasUniqueActiveSessionIndex(): bool
    {
        return collect(DB::select('SHOW INDEX FROM restaurante_sesiones_mesa'))
            ->contains(fn ($i) => $i->Key_name === 'uq_restaurante_mesa_sesion_activa');
    }

    private function crearMesaLibre(string $prefix): Mesa
    {
        return Mesa::create([
            'id_empresa' => $this->empresaId,
            'id_sucursal' => $this->user->id_sucursal,
            'numero' => $prefix . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
            'capacidad' => 4,
            'estado' => 'libre',
            'activo' => true,
            'orden' => 0,
        ]);
    }

    private function abrirSesion(Mesa $mesa): SesionMesa
    {
        Auth::login($this->user);
        $req = Request::create('/api/restaurante/sesiones-mesa', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]);
        $req->setUserResolver(fn () => $this->user);
        $resp = app(SesionMesaController::class)->store($req);
        $this->assertSame(201, $resp->getStatusCode(), $resp->getContent());
        $body = json_decode($resp->getContent(), true);

        return SesionMesa::findOrFail($body['id']);
    }

    private function productoEmpresa(): Producto
    {
        $p = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaId)
            ->where('enable', 1)
            ->orderBy('id')
            ->first();
        if (! $p) {
            $this->markTestSkipped('No hay producto enable para la empresa.');
        }

        return $p;
    }

    private function facturaIdValida(): ?int
    {
        if (! Schema::hasTable('ventas')) {
            return null;
        }
        $id = DB::table('ventas')->where('id_empresa', $this->empresaId)->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    private function cleanupMesa(Mesa $mesa): void
    {
        $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
        if ($sesionIds->isNotEmpty()) {
            $pcIds = PreCuenta::whereIn('sesion_id', $sesionIds)->pluck('id');
            if ($pcIds->isNotEmpty()) {
                PreCuentaOrdenDetalle::whereIn('pre_cuenta_id', $pcIds)->delete();
                PreCuenta::whereIn('id', $pcIds)->delete();
            }
            Comanda::whereIn('sesion_id', $sesionIds)->each(function (Comanda $c) {
                $c->detalles()->delete();
                $c->delete();
            });
            OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->forceDelete();
            SesionMesa::whereIn('id', $sesionIds)->delete();
        }
        $mesa->delete();
    }
}
