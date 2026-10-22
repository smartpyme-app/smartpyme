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

    public function test_ventas_limitado_crea_cotizacion_solo_con_permiso(): void
    {
        $conPermiso = new class {
            public string $tipo = 'Ventas Limitado';
            public function can(string $permiso): bool
            {
                return $permiso === 'ventas.cotizaciones.crear';
            }
        };
        $sinPermiso = new class {
            public string $tipo = 'Ventas Limitado';
            public function can(string $permiso): bool
            {
                return false;
            }
        };

        $this->assertTrue(CotizacionConstants::puedeCrearCotizacion($conPermiso));
        $this->assertFalse(CotizacionConstants::puedeCrearCotizacion($sinPermiso));
        $this->assertTrue(CotizacionConstants::puedeCrearCotizacion((object) ['tipo' => 'Ventas']));
    }
}
