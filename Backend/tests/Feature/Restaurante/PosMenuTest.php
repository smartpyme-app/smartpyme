<?php

namespace Tests\Feature\Restaurante;

use Tests\TestCase;

/**
 * IMPORTANTE: Este test NO usa RefreshDatabase ni ningún trait que afecte la base de datos,
 * siguiendo el patrón de los demás tests de Feature de este proyecto (la BD local de desarrollo
 * no está sincronizada con todas las migraciones, p.ej. `categoria_subcategorias` no existe
 * localmente aunque sí en el esquema real). Se valida el registro de rutas y su método HTTP;
 * la lógica de negocio se cubre manualmente/integración.
 */
final class PosMenuTest extends TestCase
{
    public function test_rutas_pos_menu_existen(): void
    {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->uri())
            ->all();

        $this->assertTrue(collect($routes)->contains(fn ($u) => str_contains($u, 'restaurante/pos-menu/categorias')));
    }

    public function test_rutas_pos_menu_tienen_shape_esperado(): void
    {
        $rutas = collect(\Illuminate\Support\Facades\Route::getRoutes())
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
}
