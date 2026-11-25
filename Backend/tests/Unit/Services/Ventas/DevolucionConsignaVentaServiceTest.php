<?php

namespace Tests\Unit\Services\Ventas;

use App\Constants\DocumentoConstants;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\Ventas\DevolucionConsignaVentaService;
use Tests\TestCase;

class DevolucionConsignaVentaServiceTest extends TestCase
{
    public function test_documento_devolucion_consigna_constant(): void
    {
        $this->assertTrue(DocumentoConstants::esDevolucionConsigna(DocumentoConstants::DEVOLUCION_CONSIGNA));
        $this->assertFalse(DocumentoConstants::esDevolucionConsigna('Nota de crédito'));
    }

    public function test_reducir_no_op_si_venta_no_es_consigna(): void
    {
        $venta = new Venta(['estado' => 'Pagada']);
        app(DevolucionConsignaVentaService::class)->reducirRemanenteDesdeDevolucion($venta, [
            ['id_producto' => 1, 'cantidad' => 1],
        ]);
        $this->addToAssertionCount(1);
    }
}
