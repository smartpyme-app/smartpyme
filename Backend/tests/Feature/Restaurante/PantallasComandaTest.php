<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PantallaRestaurante;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Una comanda por pantalla asignada al producto. La general no filtra.
 */
final class PantallasComandaTest extends TestCase
{
    private User $user;

    private int $empresaId;

    /** @var array<int, array{genera_comanda: mixed, destino_comanda: mixed, pantallas: array<int, int>}> */
    private array $productoPrev = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('restaurante_mesas') || ! Schema::hasTable('restaurante_pantallas')) {
            $this->markTestSkipped('Tablas de pantallas de comanda no disponibles.');
        }

        $user = User::whereNotNull('id_empresa')->orderBy('id')->first();
        if (! $user) {
            $this->markTestSkipped('No hay usuario con id_empresa.');
        }
        $this->user = $user;
        $this->empresaId = (int) $user->id_empresa;
        Auth::login($this->user);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function test_enviar_comanda_separa_por_pantalla_y_el_listado_filtra(): void
    {
        $cocina2 = PantallaRestaurante::create([
            'id_empresa' => $this->empresaId,
            'nombre' => 'HTP Cocina 2',
            'orden' => 90,
            'activo' => true,
        ]);
        $barra2 = PantallaRestaurante::create([
            'id_empresa' => $this->empresaId,
            'nombre' => 'HTP Barra 2',
            'orden' => 91,
            'activo' => true,
        ]);

        $productos = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaId)
            ->orderBy('id')
            ->limit(3)
            ->get();
        if ($productos->count() < 3) {
            $this->markTestSkipped('Se necesitan 3 productos en la empresa.');
        }

        [$soloCocina, $soloBarra, $ambas] = [$productos[0], $productos[1], $productos[2]];
        foreach ([$soloCocina, $soloBarra, $ambas] as $producto) {
            $this->productoPrev[$producto->id] = [
                'genera_comanda' => $producto->genera_comanda,
                'destino_comanda' => $producto->destino_comanda,
                'pantallas' => $producto->pantallasComanda()->pluck('restaurante_pantallas.id')->all(),
            ];
            $producto->genera_comanda = true;
            $producto->save();
        }
        $soloCocina->pantallasComanda()->sync([$cocina2->id]);
        $soloBarra->pantallasComanda()->sync([$barra2->id]);
        $ambas->pantallasComanda()->sync([$cocina2->id, $barra2->id]);

        $mesa = $this->crearMesaLibre('HTP-CMD');
        $sesion = $this->abrirSesion($mesa);

        foreach ([$soloCocina, $soloBarra, $ambas] as $producto) {
            OrdenDetalle::create([
                'sesion_id' => $sesion->id,
                'producto_id' => $producto->id,
                'cantidad' => 1,
                'precio_unitario' => 1,
                'enviado_cocina' => false,
                'enviado_barra' => false,
            ]);
        }

        $resp = app(ComandaController::class)->store(
            Request::create("/api/restaurante/sesiones-mesa/{$sesion->id}/comandas", 'POST'),
            $sesion->id
        );
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getContent());

        $comandas = Comanda::where('sesion_id', $sesion->id)->get();
        $this->assertCount(2, $comandas);

        $deCocina = $comandas->firstWhere('pantalla_id', $cocina2->id);
        $deBarra = $comandas->firstWhere('pantalla_id', $barra2->id);
        $this->assertNotNull($deCocina);
        $this->assertNotNull($deBarra);
        $this->assertSame(2, ComandaDetalle::where('comanda_id', $deCocina->id)->count());
        $this->assertSame(2, ComandaDetalle::where('comanda_id', $deBarra->id)->count());

        $solo = app(ComandaController::class)->index(
            Request::create('/api/restaurante/comandas', 'GET', ['pantalla_id' => $cocina2->id])
        );
        $ids = collect($solo->getData(true))->pluck('id')->all();
        $this->assertContains($deCocina->id, $ids);
        $this->assertNotContains($deBarra->id, $ids);

        $general = app(ComandaController::class)->index(Request::create('/api/restaurante/comandas', 'GET'));
        $idsGeneral = collect($general->getData(true))->pluck('id')->all();
        $this->assertContains($deCocina->id, $idsGeneral);
        $this->assertContains($deBarra->id, $idsGeneral);

        $otra = app(ComandaController::class)->store(
            Request::create("/api/restaurante/sesiones-mesa/{$sesion->id}/comandas", 'POST'),
            $sesion->id
        );
        $this->assertSame(422, $otra->getStatusCode());
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

    private function abrirSesion(Mesa $mesa): SesionMesa
    {
        $resp = app(SesionMesaController::class)->store(Request::create('/api/restaurante/sesiones-mesa', 'POST', [
            'mesa_id' => $mesa->id,
            'num_comensales' => 2,
        ]));
        $this->assertSame(201, $resp->getStatusCode(), (string) $resp->getContent());

        return SesionMesa::findOrFail($resp->getData(true)['id']);
    }

    private function cleanup(): void
    {
        if (! isset($this->empresaId)) {
            return;
        }
        try {
            $mesas = Mesa::where('id_empresa', $this->empresaId)->where('numero', 'like', 'HTP-%')->get();
            foreach ($mesas as $mesa) {
                $sesionIds = SesionMesa::where('mesa_id', $mesa->id)->pluck('id');
                if ($sesionIds->isNotEmpty()) {
                    $comandaIds = Comanda::whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($comandaIds->isNotEmpty()) {
                        DB::table('comanda_detalle_restaurante')->whereIn('comanda_id', $comandaIds)->delete();
                        Comanda::whereIn('id', $comandaIds)->delete();
                    }
                    $ordenIds = OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->pluck('id');
                    if ($ordenIds->isNotEmpty() && Schema::hasTable('restaurante_envio_pantalla')) {
                        DB::table('restaurante_envio_pantalla')->whereIn('orden_detalle_id', $ordenIds)->delete();
                    }
                    OrdenDetalle::withTrashed()->whereIn('sesion_id', $sesionIds)->forceDelete();
                    SesionMesa::whereIn('id', $sesionIds)->delete();
                }
                $mesa->delete();
            }
            $pantallaIds = PantallaRestaurante::where('id_empresa', $this->empresaId)
                ->where('nombre', 'like', 'HTP %')
                ->pluck('id');
            if ($pantallaIds->isNotEmpty()) {
                DB::table('producto_restaurante_pantalla')->whereIn('pantalla_id', $pantallaIds)->delete();
                PantallaRestaurante::whereIn('id', $pantallaIds)->delete();
            }
            foreach ($this->productoPrev as $id => $prev) {
                Producto::withoutGlobalScope('empresa')->where('id', $id)->update([
                    'genera_comanda' => $prev['genera_comanda'],
                    'destino_comanda' => $prev['destino_comanda'],
                ]);
                $producto = Producto::withoutGlobalScope('empresa')->find($id);
                $producto?->pantallasComanda()->sync($prev['pantallas']);
            }
        } catch (\Throwable) {
            // cleanup best-effort
        }
    }
}
