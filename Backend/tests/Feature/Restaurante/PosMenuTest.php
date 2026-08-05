<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\Restaurante\PosMenuController;
use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * IMPORTANTE: Este test NO usa RefreshDatabase ni ningún trait que afecte la base de datos,
 * siguiendo el patrón de los demás tests de Feature de este proyecto (las migraciones de
 * `categorias` / `productos` están comentadas, así que no hay esquema que construir en
 * memoria). Para cubrir la lógica sin filas se inspeccionan los query builders del
 * controlador (`wheres`, eager loads, orden) y el mapeo de productos, que son
 * deterministas y no abren conexión.
 */
final class PosMenuTest extends TestCase
{
    private const EMPRESA = 7;

    public function test_rutas_pos_menu_existen(): void
    {
        $routes = collect(Route::getRoutes())
            ->map(fn ($r) => $r->uri())
            ->all();

        $this->assertTrue(collect($routes)->contains(fn ($u) => str_contains($u, 'restaurante/pos-menu/categorias')));
    }

    public function test_rutas_pos_menu_tienen_shape_esperado(): void
    {
        $rutas = collect(Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'restaurante/pos-menu/'))
            ->map(fn ($r) => [
                'uri' => $r->uri(),
                'methods' => $r->methods(),
                'action' => $r->getActionName(),
            ])
            ->values();

        $esperadas = [
            'restaurante/pos-menu/categorias' => 'categorias',
            'restaurante/pos-menu/categorias/{id}/contenido' => 'contenidoCategoria',
            'restaurante/pos-menu/subcategorias/{id}/productos' => 'productosSubcategoria',
            'restaurante/pos-menu/buscar' => 'buscar',
        ];

        foreach ($esperadas as $uri => $metodoControlador) {
            $ruta = $rutas->first(fn ($r) => str_ends_with($r['uri'], $uri));

            $this->assertNotNull($ruta, "No se encontró la ruta {$uri}");
            $this->assertContains('GET', $ruta['methods']);
            $this->assertStringContainsString(
                'PosMenuController@' . $metodoControlador,
                $ruta['action']
            );
        }
    }

    public function test_categorias_raiz_filtran_por_empresa_activas_y_no_subcategorias(): void
    {
        $query = PosMenuController::queryCategoriasRaiz(self::EMPRESA);
        $wheres = $this->wheres($query);

        $this->assertSame('categorias', $query->getModel()->getTable());
        $this->assertSame(self::EMPRESA, $wheres['id_empresa']);
        $this->assertTrue($wheres['enable']);
        $this->assertSame(0, $wheres['subcategoria'], 'Las raíces son subcategoria = 0');
        $this->assertSame('nombre', $query->getQuery()->orders[0]['column']);

        $grammar = $query->getQuery()->getGrammar();
        $columnas = collect($query->getQuery()->columns)
            ->map(fn ($c) => $c instanceof Expression ? (string) $c->getValue($grammar) : (string) $c)
            ->implode(' ');
        $this->assertStringContainsString('subcategorias_count', $columnas);
        $this->assertStringContainsString('id_cate_padre', $columnas, 'El conteo sale de categorias.id_cate_padre');
        $this->assertStringNotContainsString('categoria_subcategorias', $columnas);
    }

    public function test_subcategorias_usan_id_cate_padre_en_la_tabla_categorias(): void
    {
        $query = PosMenuController::querySubcategorias(self::EMPRESA, 42);
        $wheres = $this->wheres($query);

        $this->assertSame('categorias', $query->getModel()->getTable());
        $this->assertSame(42, $wheres['id_cate_padre']);
        $this->assertSame(1, $wheres['subcategoria']);
        $this->assertSame(self::EMPRESA, $wheres['id_empresa']);
        $this->assertTrue($wheres['enable']);
        $this->assertArrayNotHasKey('categoria_id', $wheres, 'No se usa la tabla categoria_subcategorias');
    }

    public function test_no_se_usa_el_modelo_subcategoria_legacy(): void
    {
        $fuente = file_get_contents(app_path('Http/Controllers/Api/Restaurante/PosMenuController.php'));

        $this->assertStringNotContainsString('SubCategoria', $fuente);
        $this->assertStringNotContainsString('categoria_subcategorias', $fuente);
    }

    public function test_productos_de_subcategoria_usan_id_subcategoria(): void
    {
        $query = PosMenuController::queryProductosDeSubcategoria(self::EMPRESA, 99);
        $wheres = $this->wheres($query);

        $this->assertSame('productos', $query->getModel()->getTable());
        $this->assertSame(99, $wheres['id_subcategoria']);
        $this->assertArrayNotHasKey('id_categoria', $wheres);
        $this->assertSame(self::EMPRESA, $wheres['id_empresa']);
        $this->assertTrue($wheres['enable']);
    }

    public function test_productos_de_categoria_usan_id_categoria(): void
    {
        $query = PosMenuController::queryProductosDeCategoria(self::EMPRESA, 42);
        $wheres = $this->wheres($query);

        $this->assertSame(42, $wheres['id_categoria']);
        $this->assertArrayNotHasKey('id_subcategoria', $wheres);
        $this->assertSame(self::EMPRESA, $wheres['id_empresa']);
    }

    public function test_todas_las_consultas_de_productos_eager_load_imagenes(): void
    {
        $queries = [
            'base' => PosMenuController::queryProductos(self::EMPRESA),
            'categoria' => PosMenuController::queryProductosDeCategoria(self::EMPRESA, 1),
            'subcategoria' => PosMenuController::queryProductosDeSubcategoria(self::EMPRESA, 1),
        ];

        foreach ($queries as $nombre => $query) {
            $this->assertArrayHasKey('imagenes', $query->getEagerLoads(), "{$nombre} sin eager load de imagenes");
        }
    }

    public function test_busqueda_esta_scopeada_a_la_empresa(): void
    {
        $wheres = $this->wheres(PosMenuController::queryProductos(self::EMPRESA));

        $this->assertSame(self::EMPRESA, $wheres['id_empresa']);
    }

    public function test_modo_contenido_ramifica_por_subcategorias_activas(): void
    {
        $this->assertSame('productos', PosMenuController::modoContenido(0));
        $this->assertSame('subcategorias', PosMenuController::modoContenido(1));
        $this->assertSame('subcategorias', PosMenuController::modoContenido(5));
    }

    public function test_map_productos_conserva_el_shape_y_no_filtra_por_genera_comanda(): void
    {
        $plato = $this->producto(1, 'Casado', 4500, false, 'Producto', 'productos/casado.jpg');
        $servicio = $this->producto(2, 'Servicio de mesa', 500, false, 'Servicio', null);
        $servicio->genera_comanda = false;
        $plato->genera_comanda = true;

        $mapeados = PosMenuController::mapProductos(new Collection([$plato, $servicio]));

        $this->assertCount(2, $mapeados, 'Los servicios no se filtran');
        $this->assertSame(
            ['id', 'nombre', 'precio', 'img', 'tipo', 'genera_comanda'],
            array_keys($mapeados[0])
        );
        $this->assertSame('productos/casado.jpg', $mapeados[0]['img']);
        $this->assertTrue($mapeados[0]['genera_comanda']);
        $this->assertSame('productos/default.jpg', $mapeados[1]['img'], 'Sin imagen cae al placeholder');
        $this->assertFalse($mapeados[1]['genera_comanda']);
    }

    private function producto(int $id, string $nombre, float $precio, bool $comanda, string $tipo, ?string $img): Producto
    {
        $producto = new Producto([
            'nombre' => $nombre,
            'precio' => $precio,
            'tipo' => $tipo,
            'genera_comanda' => $comanda,
        ]);
        $producto->id = $id;
        // Relación precargada: replica el eager load y evita tocar la BD en el accessor `img`.
        $producto->setRelation('imagenes', new Collection($img ? [new Imagen(['img' => $img])] : []));

        return $producto;
    }

    /**
     * @return array<string, mixed> columna => valor de los where simples del builder
     */
    private function wheres(Builder $query): array
    {
        return collect($query->getQuery()->wheres)
            ->filter(fn (array $w) => isset($w['column']))
            ->mapWithKeys(fn (array $w) => [$w['column'] => $w['value'] ?? null])
            ->all();
    }
}
