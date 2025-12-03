<?php

namespace Tests\Unit\Services\Planilla;

use Tests\TestCase;
use App\Services\Planilla\PlanillaAprobacionService;
use Mockery;

/**
 * IMPORTANTE: Estos tests NO usan RefreshDatabase ni ningún trait que afecte la base de datos.
 * Los tests que requieren base de datos están marcados como skipped y se probarán en tests de integración.
 * Estos tests unitarios solo prueban lógica sin acceso a base de datos.
 */
class PlanillaAprobacionServiceTest extends TestCase
{
    protected $planillaAprobacionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planillaAprobacionService = new PlanillaAprobacionService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test aprobar planilla cambia estado correctamente
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_aprobar_planilla_cambia_estado()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test aprobar planilla solo permite borradores
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_aprobar_planilla_solo_permite_borradores()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test revertir planilla cambia estado correctamente
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_revertir_planilla_cambia_estado()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test revertir planilla solo permite aprobadas
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_revertir_planilla_solo_permite_aprobadas()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test procesar pago solo permite planillas aprobadas
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_procesar_pago_solo_permite_aprobadas()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }

    /**
     * Test procesar pago registra gastos correctamente
     * NOTA: Este test requiere base de datos configurada
     */
    public function test_procesar_pago_registra_gastos()
    {
        $this->markTestSkipped('Requiere base de datos configurada - Se probará en tests de integración');
    }
}

