<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\FusionarClientesDuplicadosService;
use PHPUnit\Framework\TestCase;

class FusionarClientesDuplicadosServiceTest extends TestCase
{
    public function test_normaliza_nit_sin_guiones_ni_espacios(): void
    {
        $this->assertSame('06141234560010', FusionarClientesDuplicadosService::normalizarDocumento('0614-123456-001-0'));
        $this->assertSame('012345678', FusionarClientesDuplicadosService::normalizarDocumento('01234567-8'));
        $this->assertSame('', FusionarClientesDuplicadosService::normalizarDocumento('  '));
        $this->assertSame('', FusionarClientesDuplicadosService::normalizarDocumento(null));
    }

    public function test_clave_usa_nit_si_hay_si_no_dui(): void
    {
        $this->assertSame('nit:06141234560010', FusionarClientesDuplicadosService::claveGrupo('0614-123456-001-0', '01234567-8'));
        $this->assertSame('dui:012345678', FusionarClientesDuplicadosService::claveGrupo(null, '01234567-8'));
        $this->assertNull(FusionarClientesDuplicadosService::claveGrupo('', ''));
    }

    public function test_un_habilitado_y_un_inhabilitado_fusiona(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 10, 'enable' => true],
            ['id' => 11, 'enable' => false],
        ]);
        $this->assertSame('fusionar', $r['accion']);
        $this->assertSame(10, $r['destino']);
        $this->assertSame([11], $r['origenes']);
    }

    public function test_dos_habilitados_salta(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 10, 'enable' => true],
            ['id' => 12, 'enable' => 1],
            ['id' => 11, 'enable' => false],
        ]);
        $this->assertSame('saltar', $r['accion']);
    }

    public function test_solo_inhabilitados_salta(): void
    {
        $r = FusionarClientesDuplicadosService::clasificarGrupo([
            ['id' => 11, 'enable' => 0],
        ]);
        $this->assertSame('saltar', $r['accion']);
        $this->assertNull($r['destino']);
    }

    public function test_tablas_reasignan_ventas_y_no_empresas(): void
    {
        $this->assertContains('ventas', FusionarClientesDuplicadosService::TABLAS_REASIGNAR);
        $this->assertContains('devoluciones_venta', FusionarClientesDuplicadosService::TABLAS_REASIGNAR);
        $this->assertNotContains('empresas', FusionarClientesDuplicadosService::TABLAS_REASIGNAR);
        $this->assertContains('cliente_ventas_mensuales', FusionarClientesDuplicadosService::TABLAS_SNAPSHOT);
    }
}
