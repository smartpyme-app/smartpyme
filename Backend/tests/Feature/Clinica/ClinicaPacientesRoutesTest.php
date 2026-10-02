<?php

namespace Tests\Feature\Clinica;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClinicaPacientesRoutesTest extends TestCase
{
    public function test_pacientes_exige_funcionalidad_y_permisos_separados(): void
    {
        $this->assertRoute('api/clinica/pacientes', 'GET', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.ver',
        ]);
        $this->assertRoute('api/clinica/pacientes', 'POST', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.crear',
        ]);
        $this->assertRoute('api/clinica/pacientes/{id}', 'PUT', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.editar',
        ]);
        $this->assertRoute('api/clinica/pacientes/{id}/estado', 'PATCH', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.desactivar',
        ]);
        $this->assertRoute('api/clinica/pacientes/{id}/responsables', 'POST', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.crear|clinica.pacientes.editar',
        ]);
        $this->assertRoute('api/clinica/pacientes/{id}/alta', 'PATCH', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.editar',
        ]);
        $this->assertRoute('api/clinica/clientes/{idCliente}/pacientes', 'GET', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.ver|ventas.clientes.ver',
        ]);
        $this->assertRoute('api/clinica/profesionales', 'GET', [
            'verificar.funcionalidad:clinica-profesionales',
            'permission:clinica.profesionales.ver|clinica.pacientes.ver',
        ]);
        $this->assertRoute('api/clinica/profesionales', 'POST', [
            'verificar.funcionalidad:clinica-profesionales',
            'permission:clinica.profesionales.editar',
        ]);
    }

    public function test_no_hay_ruta_para_borrar_pacientes(): void
    {
        $ruta = collect(Route::getRoutes())->first(function ($route) {
            return $route->uri() === 'api/clinica/pacientes/{id}' && in_array('DELETE', $route->methods(), true);
        });

        $this->assertNull($ruta);
    }

    private function assertRoute(string $uri, string $method, array $middleware): void
    {
        $route = collect(Route::getRoutes())->first(function ($route) use ($uri, $method) {
            return $route->uri() === $uri && in_array($method, $route->methods(), true);
        });

        $this->assertNotNull($route, "No se encontró {$method} {$uri}");
        foreach ($middleware as $item) {
            $this->assertContains($item, $route->gatherMiddleware(), "{$method} {$uri} no tiene {$item}");
        }
    }
}
