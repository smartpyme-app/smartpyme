<?php

namespace Tests\Unit\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Api\Planilla\ConfiguracionPlanillaController;
use App\Models\EmpresaConfiguracion;
use App\Services\Admin\EmpresaConfiguracionService;
use App\Services\Planilla\ConfiguracionPlanillaService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ConfiguracionPlanillaControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_importar_base_usa_el_pais_de_la_empresa(): void
    {
        $config = new EmpresaConfiguracion([
            'empresa_id' => 553,
            'pais' => 'CR',
            'modulo' => EmpresaConfiguracion::MODULO_PLANILLAS,
            'configuracion' => ['conceptos' => ['ccss_empleado' => []]],
        ]);

        $empresaConfigService = Mockery::mock(EmpresaConfiguracionService::class);
        $empresaConfigService
            ->shouldReceive('importarBasePlanilla')
            ->once()
            ->with(553)
            ->andReturn($config);

        $request = Request::create('/planillas/configuracion-planilla/importar-base', 'POST', [
            'cod_pais' => 'SV',
        ]);
        $request->setUserResolver(fn () => (object) ['id_empresa' => 553]);

        $controller = new ConfiguracionPlanillaController(
            Mockery::mock(ConfiguracionPlanillaService::class),
            $empresaConfigService
        );

        $response = $controller->importarBase($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('CR', $response->getData(true)['data']['pais']);
    }
}
