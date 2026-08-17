<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use App\Models\MH\Unidad;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ComandaDetalle;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\OrdenDetalle;
use App\Models\Restaurante\PreCuenta;
use App\Models\Restaurante\PreCuentaOrdenDetalle;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;
use App\Services\Restaurante\RestauranteStockService;
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

    public function test_store_item_con_presentacion_usa_precio_y_fusiona(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $pres = $this->crearPresentacion($producto, 9.5, 2, 'HT17-Pack');
        $mesa = $this->crearMesaLibre('HT17-PRE');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        $resp1 = $this->postItem($sesion->id, [
            'producto_id' => $producto->id,
            'id_presentacion' => $pres->id,
            'cantidad' => 1,
            'notas' => 'ht17-pres',
        ]);
        $this->assertSame(201, $resp1->getStatusCode(), $resp1->getContent());
        $item1 = json_decode($resp1->getContent(), true);
        $this->assertSame($pres->id, (int) $item1['id_presentacion']);
        $this->assertEqualsWithDelta(9.5, (float) $item1['precio_unitario'], 0.001);

        $resp2 = $this->postItem($sesion->id, [
            'producto_id' => $producto->id,
            'id_presentacion' => $pres->id,
            'cantidad' => 1,
            'notas' => 'ht17-pres',
        ]);
        $this->assertSame(200, $resp2->getStatusCode(), $resp2->getContent());
        $item2 = json_decode($resp2->getContent(), true);
        $this->assertSame($item1['id'], $item2['id']);
        $this->assertEqualsWithDelta(2.0, (float) $item2['cantidad'], 0.001);
        $this->assertSame(1, OrdenDetalle::where('sesion_id', $sesion->id)->count());
    }

    public function test_store_item_presentacion_de_otro_producto_422(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $otro = $this->otroProductoEmpresa($producto->id);
        $presAjena = $this->crearPresentacion($otro, 9.5, 2, 'HT17-Ajena');
        $mesa = $this->crearMesaLibre('HT17-AJN');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        $resp = $this->postItem($sesion->id, [
            'producto_id' => $producto->id,
            'id_presentacion' => $presAjena->id,
            'cantidad' => 1,
            'notas' => 'ht17-ajena',
        ]);
        $this->assertSame(422, $resp->getStatusCode(), $resp->getContent());
        $this->assertSame(0, OrdenDetalle::where('sesion_id', $sesion->id)->count());
    }

    public function test_store_no_fusiona_linea_null_con_presentacion(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $precio = round((float) ($producto->precio ?? 0), 2);
        $pres = $this->crearPresentacion($producto, $precio, 2, 'HT17-Dist');
        $mesa = $this->crearMesaLibre('HT17-DST');
        $sesion = $this->abrirSesion($mesa, $this->userA);

        $sinPres = $this->postItem($sesion->id, [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'notas' => 'ht17-dist',
        ]);
        $this->assertSame(201, $sinPres->getStatusCode(), $sinPres->getContent());

        $conPres = $this->postItem($sesion->id, [
            'producto_id' => $producto->id,
            'id_presentacion' => $pres->id,
            'cantidad' => 1,
            'notas' => 'ht17-dist',
        ]);
        $this->assertSame(201, $conPres->getStatusCode(), $conPres->getContent());

        $lineas = OrdenDetalle::where('sesion_id', $sesion->id)->orderBy('id')->get();
        $this->assertCount(2, $lineas);
        $this->assertNull($lineas[0]->id_presentacion);
        $this->assertSame($pres->id, (int) $lineas[1]->id_presentacion);
    }

    public function test_update_cantidad_con_presentacion_valida_stock_base(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        if (($producto->tipo ?? '') === 'Servicio') {
            $this->markTestSkipped('Producto servicio no valida stock.');
        }
        $empresa = Empresa::find($this->empresaA);
        if (! $empresa) {
            $this->markTestSkipped('Empresa no encontrada.');
        }
        $prevVender = $empresa->vender_sin_stock;
        $empresa->update(['vender_sin_stock' => 0]);
        try {
            $pres = $this->crearPresentacion($producto, 9.5, 2, 'HT17-Stk');
            $mesa = $this->crearMesaLibre('HT17-STK');
            $sesion = $this->abrirSesion($mesa, $this->userA);
            $bodegaId = app(RestauranteStockService::class)->resolverIdBodega($sesion, $this->userA);
            if (! $bodegaId) {
                $this->markTestSkipped('Sin bodega para validar stock.');
            }
            $inv = Inventario::where('id_producto', $producto->id)->where('id_bodega', $bodegaId)->first();
            if (! $inv) {
                $this->markTestSkipped('Sin inventario del producto en bodega.');
            }
            $prevStock = $inv->stock;
            $inv->update(['stock' => 3]);
            try {
                $resp = $this->postItem($sesion->id, [
                    'producto_id' => $producto->id,
                    'id_presentacion' => $pres->id,
                    'cantidad' => 1,
                    'notas' => 'ht17-stk',
                ]);
                $this->assertSame(201, $resp->getStatusCode(), $resp->getContent());
                $item = json_decode($resp->getContent(), true);
                $upd = $this->putItem($sesion->id, (int) $item['id'], ['cantidad' => 2]);
                $this->assertSame(422, $upd->getStatusCode(), $upd->getContent());
            } finally {
                $inv->update(['stock' => $prevStock]);
            }
        } finally {
            $empresa->update(['vender_sin_stock' => $prevVender]);
        }
    }

    public function test_presentacion_no_genera_comanda_si_el_producto_no_lo_hace(): void
    {
        $producto = $this->productoEmpresa($this->empresaA);
        $prev = $producto->genera_comanda;
        $producto->genera_comanda = false;
        $producto->save();
        try {
            $pres = $this->crearPresentacion($producto, 1, 1, 'HT17-NoCmd');
            $mesa = $this->crearMesaLibre('HT17-CMD');
            $sesion = $this->abrirSesion($mesa, $this->userA);
            $add = Request::create('/x', 'POST', [
                'producto_id' => $producto->id,
                'id_presentacion' => $pres->id,
                'cantidad' => 1,
            ]);
            $add->headers->set('Idempotency-Key', 'ht17-cmd-'.bin2hex(random_bytes(8)));
            $add->setUserResolver(fn () => $this->userA);
            $this->assertSame(201, app(OrdenDetalleController::class)->store($add, $sesion->id)->getStatusCode());
            $cmd = Request::create('/api/restaurante/sesiones-mesa/'.$sesion->id.'/comandas', 'POST', []);
            $cmd->headers->set('Idempotency-Key', 'ht17-send-'.bin2hex(random_bytes(8)));
            $cmd->setUserResolver(fn () => $this->userA);
            $resp = app(\App\Http\Controllers\Api\Restaurante\ComandaController::class)->store($cmd, $sesion->id);
            $body = json_decode($resp->getContent(), true);
            $this->assertSame(0, Comanda::where('sesion_id', $sesion->id)->whereIn('destino', ['cocina', 'barra', 'ambos'])->count(), json_encode($body));
        } finally {
            $producto->genera_comanda = $prev;
            $producto->save();
        }
    }

    private function crearPresentacion(Producto $producto, float $precio, float $factor, string $nombre): ProductoPresentacion
    {
        $pres = ProductoPresentacion::create([
            'id_producto' => $producto->id,
            'id_unidad_medida' => $this->unidadIdValida($producto),
            'nombre_comercial' => $nombre,
            'factor_conversion' => $factor,
            'precio_venta' => $precio,
        ]);
        $this->presentacionIds[] = $pres->id;

        return $pres;
    }

    private function unidadIdValida(Producto $producto): int
    {
        $medida = (int) ($producto->medida ?: 0);
        if ($medida > 0 && Unidad::whereKey($medida)->exists()) {
            return $medida;
        }
        $id = Unidad::query()->orderBy('id')->value('id');
        if (! $id) {
            $this->markTestSkipped('Sin unidades.');
        }

        return (int) $id;
    }

    private function otroProductoEmpresa(int $exceptoId): Producto
    {
        $p = Producto::withoutGlobalScope('empresa')
            ->where('id_empresa', $this->empresaA)
            ->where('id', '!=', $exceptoId)
            ->orderBy('id')
            ->first();
        if (! $p) {
            $this->markTestSkipped('Se necesita un segundo producto de la empresa.');
        }

        return $p;
    }

    private function postItem(int $sesionId, array $payload): \Illuminate\Http\JsonResponse
    {
        Auth::login($this->userA);
        $req = Request::create('/x', 'POST', $payload);
        $req->headers->set('Idempotency-Key', 'ht17-item-'.bin2hex(random_bytes(8)));
        $req->setUserResolver(fn () => $this->userA);

        return app(OrdenDetalleController::class)->store($req, $sesionId);
    }

    private function putItem(int $sesionId, int $itemId, array $payload): \Illuminate\Http\JsonResponse
    {
        Auth::login($this->userA);
        $req = Request::create('/x', 'PUT', $payload);
        $req->setUserResolver(fn () => $this->userA);

        return app(OrdenDetalleController::class)->update($req, $sesionId, $itemId);
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
