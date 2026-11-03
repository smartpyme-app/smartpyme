<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Constants\CotizacionConstants;
use Tests\TestCase;

class CotizacionVentasLimitadoAccionesTest extends TestCase
{
    public function test_solo_factura_pendiente_e_imprime_facturada(): void
    {
        $this->assertSame('facturar', CotizacionConstants::accionVentasLimitado('Pendiente'));
        $this->assertSame('imprimir', CotizacionConstants::accionVentasLimitado('Facturada'));
        $this->assertNull(CotizacionConstants::accionVentasLimitado('Anulada'));
        $this->assertSame('facturar', CotizacionConstants::accionVentasLimitado('Enviada'));
        $this->assertSame('facturar', CotizacionConstants::accionVentasLimitado('pendiente'));
        $this->assertSame(CotizacionConstants::ESTADO_PENDIENTE, CotizacionConstants::normalizarEstado('aceptada'));

        $this->assertTrue(CotizacionConstants::esVentasLimitado((object) ['tipo' => 'Ventas Limitado']));
        $this->assertFalse(CotizacionConstants::esVentasLimitado((object) ['tipo' => 'Ventas']));
        $this->assertFalse(CotizacionConstants::esVentasLimitado(null));

        $ventasConRolLimitado = new class {
            public string $tipo = 'Ventas';
            public function hasRole(string $role): bool
            {
                return $role === 'ventas_limitado';
            }
        };
        $this->assertTrue(CotizacionConstants::esVentasLimitado($ventasConRolLimitado));
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

    public function test_ventas_y_ventas_limitado_filtran_listado_por_usuario(): void
    {
        $this->assertTrue(CotizacionConstants::soloListadoCotizacionesPropias((object) ['tipo' => 'Ventas']));
        $this->assertTrue(CotizacionConstants::soloListadoCotizacionesPropias((object) ['tipo' => 'Ventas Limitado']));
        $this->assertFalse(CotizacionConstants::soloListadoCotizacionesPropias((object) ['tipo' => 'Administrador']));

        $ventasConRolLimitado = new class {
            public string $tipo = 'Ventas';
            public function hasRole(string $role): bool
            {
                return $role === 'ventas_limitado';
            }
        };
        $this->assertTrue(CotizacionConstants::soloListadoCotizacionesPropias($ventasConRolLimitado));
    }
}
