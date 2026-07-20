<?php

namespace Tests\Unit\Services\Planilla;

use PHPUnit\Framework\TestCase;

/**
 * Regresión: generarBoletaIndividual debe usar la vista existente y el contrato de variables
 * que consume pdf/boleta-individual.blade.php (no la vista fantasma del refactor).
 */
class PlanillaExportBoletaIndividualTest extends TestCase
{
    public function test_servicio_usa_vista_boleta_individual_existente(): void
    {
        $servicePath = dirname(__DIR__, 4) . '/app/Services/Planilla/PlanillaExportService.php';
        $viewPath = dirname(__DIR__, 4) . '/resources/views/pdf/boleta-individual.blade.php';
        $source = file_get_contents($servicePath);

        $this->assertFileExists($viewPath);
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 4) . '/resources/views/pdf/boleta-pago-individual.blade.php'
        );
        $this->assertStringContainsString("loadView('pdf.boleta-individual'", $source);
        $this->assertStringNotContainsString("loadView('pdf.boleta-pago-individual'", $source);
        $this->assertStringContainsString("'totalIngresos'", $source);
        $this->assertStringContainsString("'totalDeducciones'", $source);
        $this->assertStringContainsString("'periodo'", $source);
    }
}
