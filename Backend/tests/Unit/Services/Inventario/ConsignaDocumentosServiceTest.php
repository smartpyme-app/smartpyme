<?php

namespace Tests\Unit\Services\Inventario;

use App\Constants\DocumentoConstants;
use App\Services\Inventario\ConsignaDocumentosService;
use Tests\TestCase;

class ConsignaDocumentosServiceTest extends TestCase
{
    public function test_nombres_ledger_incluyen_devolucion_consigna(): void
    {
        $this->assertContains(
            DocumentoConstants::DEVOLUCION_CONSIGNA,
            ConsignaDocumentosService::NOMBRES_LEDGER
        );
    }
}
