<?php

namespace Tests\Unit\Services\Admin;

use App\Models\EmpresaConfiguracion;
use App\Services\Planilla\PlanillaTemplatesService;
use PHPUnit\Framework\TestCase;

/**
 * Check mínimo: plantilla CR ≠ SV y país desconocido no cae a SV.
 */
class EmpresaConfiguracionServiceCheck extends TestCase
{
    public function test_plantilla_cr_no_es_sv(): void
    {
        $cr = PlanillaTemplatesService::getConfiguracionPorPais('CR');
        $sv = PlanillaTemplatesService::getConfiguracionPorPais('SV');

        $this->assertNotEquals($sv, $cr);
        $this->assertSame(EmpresaConfiguracion::MODULO_PLANILLAS, 'planillas');
        $this->assertSame(\App\Models\PaisConfiguracion::MODULO_PLANILLAS, 'planillas');
    }

    public function test_pais_desconocido_lanza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlanillaTemplatesService::plantilla('XX');
    }

    public function test_empresa_sin_cod_pais_resuelve_desde_nombre(): void
    {
        $empresa = new \App\Models\Admin\Empresa(['pais' => 'Costa Rica']);

        $this->assertSame('CR', \App\Models\EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa($empresa));
        $this->assertSame('SV', \App\Models\EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa(null));
    }
}
