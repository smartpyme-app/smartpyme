<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No toca BD: valida que las rutas registren middleware Spatie y, donde aplica, funcionalidad.
 */
final class ModulosOperativosPermissionsTest extends TestCase
{
    public function test_consignas_requiere_permiso_de_lectura(): void
    {
        $this->assertRouteHasPermission('api/productos/consignas', 'GET', 'permission:consignas.ver');
        $this->assertRouteHasPermission('api/productos/consignas-compras', 'GET', 'permission:consignas.ver');
    }

    public function test_planillas_registros_empleados_y_configuracion_tienen_permisos(): void
    {
        $this->assertRouteHasPermission('api/planillas', 'GET', 'permission:planilla.registros.ver');
        $this->assertRouteHasPermission('api/planillas', 'POST', 'permission:planilla.registros.crear');
        $this->assertRouteHasPermission('api/empleados', 'GET', 'permission:planilla.empleados.ver');
        $this->assertRouteHasPermission('api/empleados', 'POST', 'permission:planilla.empleados.crear');
        $this->assertRouteHasPermission('api/planillas/configuracion-planilla', 'GET', 'permission:planilla.configuracion.ver');
        $this->assertRouteHasPermission('api/aguinaldos', 'GET', 'permission:planilla.registros.ver');
        $this->assertRouteHasPermission('api/planillas/prestamos', 'GET', 'permission:planilla.registros.ver');
    }

    public function test_restaurante_y_pedidos_combinan_funcionalidad_y_permisos_independientes(): void
    {
        $this->assertRouteHasPermission('api/restaurante/mesas', 'GET', 'permission:restaurante.ver');
        $this->assertRouteHasPermission('api/restaurante/mesas', 'POST', 'permission:restaurante.crear');
        $this->assertRouteHasMiddleware('api/restaurante/mesas', 'GET', 'verificar.funcionalidad:modulo-restaurante');

        $this->assertRouteHasPermission('api/restaurante/pedidos', 'GET', 'permission:pedidos.ver');
        $this->assertRouteHasPermission('api/restaurante/pedidos', 'POST', 'permission:pedidos.crear');
        $this->assertRouteHasPermission('api/restaurante/pedidos/{id}', 'DELETE', 'permission:pedidos.eliminar');
        $this->assertRouteHasMiddleware('api/restaurante/pedidos', 'GET', 'verificar.funcionalidad:modulo-restaurante');
    }

    private function assertRouteHasPermission(string $uri, string $method, string $permissionMiddleware): void
    {
        $this->assertRouteHasMiddleware($uri, $method, $permissionMiddleware);
    }

    private function assertRouteHasMiddleware(string $uri, string $method, string $middleware): void
    {
        $route = collect(Route::getRoutes())
            ->first(function ($route) use ($uri, $method) {
                return $route->uri() === $uri && in_array($method, $route->methods(), true);
            });

        $this->assertNotNull($route, "No se encontró la ruta {$method} {$uri}");
        $this->assertContains(
            $middleware,
            $route->gatherMiddleware(),
            "La ruta {$method} {$uri} no tiene middleware {$middleware}"
        );
    }
}
