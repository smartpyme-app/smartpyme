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

    public function test_update_usa_el_pais_de_la_empresa_e_ignora_cod_pais_del_request(): void
    {
        $conceptos = [
            'ccss_empleado' => [
                'nombre' => 'CCSS Empleado',
                'tipo' => 'porcentaje',
                'es_deduccion' => true,
            ],
        ];
        $configuracion = ['conceptos' => $conceptos];

        $saved = new EmpresaConfiguracion([
            'empresa_id' => 553,
            'pais' => 'CR',
            'modulo' => EmpresaConfiguracion::MODULO_PLANILLAS,
            'configuracion' => $configuracion,
        ]);
        $saved->id = 10;
        $saved->updated_at = now();

        $empresaConfigService = Mockery::mock(EmpresaConfiguracionService::class);
        $empresaConfigService
            ->shouldReceive('paisEmpresa')
            ->once()
            ->with(553)
            ->andReturn('CR');
        $empresaConfigService
            ->shouldReceive('set')
            ->once()
            ->with(553, EmpresaConfiguracion::MODULO_PLANILLAS, $configuracion, 'CR')
            ->andReturn($saved);

        $configuracionService = Mockery::mock(ConfiguracionPlanillaService::class);
        $configuracionService
            ->shouldReceive('validarConfiguracion')
            ->once()
            ->with(553)
            ->andReturn(['valida' => true, 'mensaje' => '']);

        $request = \App\Http\Requests\Planilla\UpdateConfiguracionPlanillaRequest::create(
            '/planillas/configuracion-planilla',
            'POST',
            [
                'cod_pais' => 'SV',
                'configuracion' => $configuracion,
            ]
        );
        $request->setUserResolver(fn () => (object) ['id_empresa' => 553]);

        $controller = new ConfiguracionPlanillaController(
            $configuracionService,
            $empresaConfigService
        );

        $response = $controller->update($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('CR', $response->getData(true)['data']['pais']);
    }
}
