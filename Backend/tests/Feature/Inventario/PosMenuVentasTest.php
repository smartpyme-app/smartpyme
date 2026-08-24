<?php

namespace Tests\Feature\Inventario;

use App\Http\Controllers\Api\Inventario\PosMenuVentasController;
use App\Models\Inventario\Producto;
use App\Support\Inventario\PosMenuCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PosMenuVentasTest extends TestCase
{
    private const EMPRESA = 7;

    public function test_rutas_inventario_pos_menu_existen(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        $this->assertTrue(collect($routes)->contains(fn ($u) => str_contains($u, 'inventario/pos-menu/categorias')));
        $this->assertTrue(collect($routes)->contains(fn ($u) => str_contains($u, 'inventario/pos-menu/productos/{id}')));
    }

    public function test_resolver_porcentaje_impuesto_usa_empresa_si_producto_vacio(): void
    {
        $this->assertSame(15.0, PosMenuVentasController::resolverPorcentajeImpuesto(null, 15.0));
        $this->assertSame(15.0, PosMenuVentasController::resolverPorcentajeImpuesto('', 15.0));
    }

    public function test_resolver_porcentaje_impuesto_respeta_cero_explicito(): void
    {
        $this->assertSame(0.0, PosMenuVentasController::resolverPorcentajeImpuesto(0, 15.0));
    }

    public function test_precio_tile_incluye_iva(): void
    {
        $producto = new Producto([
            'precio' => 100,
            'porcentaje_impuesto' => 13,
        ]);

        $mapped = PosMenuCatalog::mapProductos(
            new \Illuminate\Database\Eloquent\Collection([$producto]),
            false,
            fn (Producto $p, $precioSinIva) => PosMenuVentasController::resolverPorcentajeImpuesto($p->porcentaje_impuesto, 15.0) > 0
                ? round((float) $precioSinIva * 1.13, 2)
                : (float) $precioSinIva
        );

        $this->assertSame(113.0, $mapped[0]['precio']);
    }

    public function test_query_categorias_raiz_filtra_activas(): void
    {
        $query = PosMenuCatalog::queryCategoriasRaiz(self::EMPRESA);
        $wheres = collect($query->getQuery()->wheres)->pluck('column', 'column');

        $this->assertTrue($wheres->has('id_empresa'));
        $this->assertTrue($wheres->has('enable'));
        $this->assertTrue($wheres->has('subcategoria'));
    }
}
