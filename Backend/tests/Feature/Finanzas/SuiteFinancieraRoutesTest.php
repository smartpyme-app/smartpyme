<?php

namespace Tests\Feature\Finanzas;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuiteFinancieraRoutesTest extends TestCase
{
    public function test_modelado_exige_funcionalidad_suite_financiera(): void
    {
        $rutas = [
            ['api/finanzas/modelado', 'GET'],
            ['api/finanzas/modelado/clasificacion', 'PUT'],
            ['api/finanzas/modelado/clasificacion', 'DELETE'],
            ['api/finanzas/modelado/clasificacion/eliminar', 'POST'],
            ['api/finanzas/modelado/calendario', 'POST'],
            ['api/finanzas/modelado/calendario/{id}', 'PUT'],
            ['api/finanzas/modelado/calendario/{id}', 'DELETE'],
            ['api/finanzas/modelado/presupuesto', 'PUT'],
            ['api/finanzas/modelado/pronostico', 'PUT'],
        ];

        foreach ($rutas as [$uri, $method]) {
            $route = collect(Route::getRoutes())
                ->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods(), true));

            $this->assertNotNull($route, "No se encontró la ruta {$method} {$uri}");
            $this->assertContains(
                'verificar.funcionalidad:suite-financiera',
                $route->gatherMiddleware(),
                "La ruta {$method} {$uri} no exige verificar.funcionalidad:suite-financiera"
            );
        }
    }
}
