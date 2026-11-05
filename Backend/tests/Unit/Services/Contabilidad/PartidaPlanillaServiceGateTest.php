<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Models\Planilla\Planilla;
use App\Services\Contabilidad\PartidaPlanillaService;
use Tests\TestCase;

class PartidaPlanillaServiceGateTest extends TestCase
{
    public function test_generar_partida_planilla_no_opera_cuando_el_gate_esta_apagado(): void
    {
        $service = $this->getMockBuilder(PartidaPlanillaService::class)
            ->onlyMethods(['debeGenerarPartidasAutomaticasPlanilla'])
            ->getMock();

        $service->method('debeGenerarPartidasAutomaticasPlanilla')
            ->with(99)
            ->willReturn(false);

        $planilla = new Planilla();
        $planilla->id = 1;
        $planilla->id_empresa = 99;

        $this->assertNull($service->generarPartidaPlanilla($planilla));
    }
}
