<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Constants\CotizacionConstants;
use Tests\TestCase;

class CotizacionVentasLimitadoAccionesTest extends TestCase
{
    public function test_solo_factura_pendiente_e_imprime_facturada(): void
    {
        $this->assertSame('facturar', CotizacionConstants::accionVentasLimitado('Pendiente'));
        $this->assertSame('imprimir', CotizacionConstants::accionVentasLimitado('facturada'));
        $this->assertNull(CotizacionConstants::accionVentasLimitado('anulada'));
        $this->assertNull(CotizacionConstants::accionVentasLimitado('enviada'));
        $this->assertNull(CotizacionConstants::accionVentasLimitado('aceptada'));

        $this->assertTrue(CotizacionConstants::esVentasLimitado((object) ['tipo' => 'Ventas Limitado']));
        $this->assertFalse(CotizacionConstants::esVentasLimitado((object) ['tipo' => 'Ventas']));
        $this->assertFalse(CotizacionConstants::esVentasLimitado(null));
    }
}
