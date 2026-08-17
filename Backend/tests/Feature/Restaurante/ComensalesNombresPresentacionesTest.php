<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ComensalesNombresPresentacionesTest extends TestCase
{
    private User $userA;

    private int $empresaA;

    /** @var list<int> */
    private array $presentacionIds = [];

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
        Auth::login($this->userA);
    }

    protected function tearDown(): void
    {
        $this->cleanupHt17();
        parent::tearDown();
    }

    public function test_update_comensales_en_abierta_persiste(): void
    {
        $mesa = $this->crearMesaLibre('HT17-COM');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id, 'PUT', [
            'num_comensales' => 5,
        ]);
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(SesionMesaController::class)->update($req, $sesion->id);
        $this->assertSame(200, $resp->getStatusCode(), $resp->getContent());
        $this->assertSame(5, (int) $sesion->fresh()->num_comensales);
    }

    public function test_update_comensales_en_cerrada_422(): void
    {
        $mesa = $this->crearMesaLibre('HT17-CLS');
        $sesion = $this->abrirSesion($mesa, $this->userA);
        $sesion->update(['estado' => 'cerrada', 'closed_at' => now()]);
        $req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id, 'PUT', [
            'num_comensales' => 8,
        ]);
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(SesionMesaController::class)->update($req, $sesion->id);
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame(2, (int) $sesion->fresh()->num_comensales);
    }

    public function test_dividir_equitativa_persiste_nombres(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $mesa = $this->crearMesaLibre('HT17-DIV');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        Auth::login($this->userA);
        $add = Request::create('/x', 'POST', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'notas' => 'ht17-div',
        ]);
        $add->setUserResolver(fn () => $this->userA);
        $addResp = app(OrdenDetalleController::class)->store($add, $sesion->id);
        $this->assertSame(201, $addResp->getStatusCode(), $addResp->getContent());

        $req = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id.'/pre-cuenta', 'POST', [
            'dividir' => [
                'tipo' => 'equitativa',
                'num_pagadores' => 2,
                'nombres' => ['Ana', ''],
            ],
        ]);
        $req->headers->set('Idempotency-Key', 'ht17-div-'.bin2hex(random_bytes(8)));
        $req->setUserResolver(fn () => $this->userA);
        $resp = app(PreCuentaController::class)->generar($req, $sesion->id);
        $this->assertSame(201, $resp->getStatusCode(), $resp->getContent());
        $pcs = PreCuenta::where('sesion_id', $sesion->id)->orderBy('id')->get();
        $this->assertSame('Ana', $pcs[0]->nombre_pagador);
        $this->assertSame('Persona 2', $pcs[1]->nombre_pagador);
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

    private function cleanupHt17(): void
    {
        try {
            if ($this->presentacionIds !== []) {
                ProductoPresentacion::whereIn('id', $this->presentacionIds)->delete();
            }
            $mesas = Mesa::where('numero', 'like', 'HT17-%')->get();
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
        }
    }
}
