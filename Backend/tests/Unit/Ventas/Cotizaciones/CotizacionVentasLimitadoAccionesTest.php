<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Constants\CotizacionConstants;
use Tests\TestCase;

class CotizacionVentasLimitadoAccionesTest extends TestCase
{
    public function test_detecta_ventas_limitado_y_normaliza_estado(): void
    {
        $this->assertSame(CotizacionConstants::ESTADO_PENDIENTE, CotizacionConstants::normalizarEstado('aceptada'));
        $this->assertSame(CotizacionConstants::ESTADO_PENDIENTE, CotizacionConstants::normalizarEstado('pendiente'));

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

    public function test_ventas_limitado_crea_edita_e_imprime_solo_con_permiso(): void
    {
        $soloCrear = $this->ventasLimitadoCon(['ventas.cotizaciones.crear']);
        $soloEditar = $this->ventasLimitadoCon(['ventas.cotizaciones.editar']);
        $soloVer = $this->ventasLimitadoCon(['ventas.cotizaciones.ver']);
        $sinPermiso = $this->ventasLimitadoCon([]);

        $this->assertTrue(CotizacionConstants::puedeGuardarCotizacion($soloCrear, true));
        $this->assertFalse(CotizacionConstants::puedeGuardarCotizacion($soloCrear, false));
        $this->assertTrue(CotizacionConstants::puedeGuardarCotizacion($soloEditar, false));
        $this->assertFalse(CotizacionConstants::puedeGuardarCotizacion($soloEditar, true));
        $this->assertFalse(CotizacionConstants::puedeGuardarCotizacion($sinPermiso, true));
        $this->assertFalse(CotizacionConstants::puedeGuardarCotizacion($sinPermiso, false));
        $this->assertTrue(CotizacionConstants::puedeGuardarCotizacion((object) ['tipo' => 'Ventas'], false));

        $this->assertTrue(CotizacionConstants::puedeImprimirCotizacion($soloVer));
        $this->assertTrue(CotizacionConstants::puedeImprimirCotizacion($soloEditar));
        $this->assertFalse(CotizacionConstants::puedeImprimirCotizacion($soloCrear));
        $this->assertTrue(CotizacionConstants::puedeImprimirCotizacion((object) ['tipo' => 'Ventas']));
    }

    private function ventasLimitadoCon(array $permisos): object
    {
        return new class($permisos) {
            public string $tipo = 'Ventas Limitado';
            public function __construct(private array $permisos) {}
            public function can(string $permiso): bool
            {
                return in_array($permiso, $this->permisos, true);
            }
        };
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
