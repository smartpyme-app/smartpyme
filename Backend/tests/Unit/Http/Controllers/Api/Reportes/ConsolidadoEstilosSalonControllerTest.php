<?php

namespace Tests\Unit\Http\Controllers\Api\Reportes;

use App\Http\Controllers\Api\Reportes\ConsolidadoEstilosSalonController;
use App\Services\EstilosSalon\ConsolidadoEstilosSalonService;
use App\Support\EstilosSalon\EstilosSalonPeriodo;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConsolidadoEstilosSalonControllerTest extends TestCase
{
    public function test_excel_rechaza_empresa_que_no_es_estilos(): void
    {
        $request = Request::create('/api/reporte/estilos-salon/consolidado/excel', 'GET', [
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-08-19',
        ]);
        $request->setUserResolver(static fn () => (object) ['id_empresa' => 1]);

        $controller = new ConsolidadoEstilosSalonController(
            $this->createMock(ConsolidadoEstilosSalonService::class)
        );

        $response = $controller->excel($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse(EstilosSalonPeriodo::empresaPermitida(1));
    }
}
