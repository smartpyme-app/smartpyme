<?php

namespace Tests\Feature\Clinica;

use App\Services\Clinica\ClinicaPermisos;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sin BD: contrato de middleware de la fase 1 clínica.
 */
final class ClinicaFase1PermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('permissions', require config_path('permissions.php'));
    }

    public function test_expediente_se_filtra_en_api_sin_ruta_extra(): void
    {
        $this->assertRoute('api/clinica/pacientes/{id}', 'GET', [
            'verificar.funcionalidad:clinica-pacientes',
            'permission:clinica.pacientes.ver',
        ]);
    }

    public function test_desactivar_exige_permiso_distinto_de_editar(): void
    {
        $this->assertRoute('api/clinica/pacientes/{id}/estado', 'PATCH', [
            'permission:clinica.pacientes.desactivar',
        ]);
        $this->assertRoute('api/clinica/pacientes/{id}', 'PUT', [
            'permission:clinica.pacientes.editar',
        ]);
    }

    public function test_catalogo_expediente_existe_en_config(): void
    {
        $this->assertSame(ClinicaPermisos::EXPEDIENTE_VER, config('permissions.PERMISSION_CLINICA.expediente.ver'));
    }

    /** @param list<string> $middleware */
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
